"""
Email Parser Service

Handles parsing of Outlook email files (.msg via extract_msg, .eml via stdlib email).
Provides comprehensive email data extraction including metadata, content, and attachments.
"""

import sys
import os
import re
import base64
import email
from email import policy
from email.message import Message
from email.utils import getaddresses, parsedate_to_datetime
from pathlib import Path
from datetime import datetime, timezone
from typing import Dict, Any, Optional, Tuple, Iterator, List

try:
    import extract_msg
    from extract_msg.enums import ErrorBehavior
except ImportError as e:
    print(f"Warning: extract_msg not installed: {e}")
    ErrorBehavior = None

try:
    import olefile
except ImportError:
    olefile = None

try:
    import compressed_rtf
except ImportError:
    compressed_rtf = None

from utils.logger import setup_logger

logger = setup_logger(__name__, 'email_parser.log')


class EmailParserService:
    """Service for parsing Outlook email files (.msg, .eml)."""
    
    def __init__(self):
        logger.info("Email Parser Service initialized")

    def parse_email_file(self, file_path: str) -> Dict[str, Any]:
        """Parse an Outlook email file based on its extension."""
        extension = Path(file_path).suffix.lower()
        if extension == '.eml':
            return self.parse_eml_file(file_path)
        return self.parse_msg_file(file_path)

    @staticmethod
    def _is_recoverable_codec_failure(exc: BaseException) -> bool:
        """True when extract_msg failed due to string decoding (e.g. wrong cp950 assumption)."""
        if isinstance(exc, (UnicodeDecodeError, UnicodeError)):
            return True
        msg = str(exc).lower()
        return (
            "codec can't decode" in msg
            or "illegal multibyte sequence" in msg
            or "unexpected end of data" in msg
        )

    @staticmethod
    def _skip_rtf_deencapsulation(_rtf_body: bytes, _body_type: Any) -> None:
        """Bypass RTFDE de-encapsulation (avoids cp950 / chardet mis-detection on some servers)."""
        return None

    def _base_message_kwargs(self) -> Dict[str, Any]:
        """Shared safe open options — delay attachment init and tolerate broken parts."""
        kwargs: Dict[str, Any] = {
            'delayAttachments': True,
            'ignoreRtfDeErrors': True,
        }
        if ErrorBehavior is not None:
            kwargs['errorBehavior'] = ErrorBehavior.ATTACH_SUPPRESS_ALL
        return kwargs

    def _message_open_strategies(self) -> Iterator[Tuple[str, Dict[str, Any]]]:
        """
        Try progressively safer parse options when charset/decode fails (e.g. cp950 on Linux).

        delayAttachments is always set so Message() does not load attachments during __init__
        (a common source of codec errors before body/html are read).
        """
        base = self._base_message_kwargs()

        yield ("default", dict(base))
        yield ("override_utf8", {**base, "overrideEncoding": "utf-8"})
        yield ("override_utf16_le", {**base, "overrideEncoding": "utf-16-le"})
        yield ("override_cp1252", {**base, "overrideEncoding": "cp1252"})
        yield ("override_latin1", {**base, "overrideEncoding": "latin-1"})
        yield ("skip_rtf_deencap", {
            **base,
            "overrideEncoding": "latin-1",
            "deencapsulationFunc": self._skip_rtf_deencapsulation,
        })

    def _read_msg_property(self, msg: Any, attr: str, default: Any = None) -> Any:
        """Read a message attribute; return default on codec/decode failures."""
        try:
            if not hasattr(msg, attr):
                return default
            return getattr(msg, attr)
        except Exception as exc:
            if self._is_recoverable_codec_failure(exc):
                logger.warning("Could not read msg.%s: %s", attr, exc)
                return default
            raise

    def _decode_stream_text(self, raw: Optional[bytes]) -> str:
        if not raw:
            return ''
        if raw.startswith(b'\xff\xfe') or raw.startswith(b'\xfe\xff'):
            return raw.decode('utf-16-le', errors='replace').strip('\x00')
        for encoding in ('utf-16-le', 'utf-8', 'cp1252', 'latin-1'):
            try:
                return raw.decode(encoding).strip('\x00')
            except UnicodeDecodeError:
                continue
        return raw.decode('latin-1', errors='replace').strip('\x00')

    def _read_stream_from_msg(self, msg: Any, stream_base: str) -> Optional[bytes]:
        try:
            getter = getattr(msg, '_getStream', None)
            if getter is None:
                return None
            for suffix in ('001F', '001E', '0102', '101F'):
                data = getter(f'{stream_base}{suffix}')
                if data:
                    return data
        except Exception as exc:
            logger.warning("Direct stream read failed for %s: %s", stream_base, exc)
        return None

    def _fill_body_from_streams(self, msg: Any, email_data: Dict[str, Any]) -> None:
        """Fallback when extract_msg body/html properties fail (RTFDE / cp950 issues)."""
        if not email_data.get('text_content'):
            plain = self._read_stream_from_msg(msg, '__substg1.0_1000')
            if plain:
                email_data['text_content'] = self._decode_stream_text(plain)

        if not email_data.get('html_content'):
            html_bin = self._read_stream_from_msg(msg, '__substg1.0_1013')
            if html_bin:
                email_data['html_content'] = self._decode_stream_text(html_bin)

        if not email_data.get('text_content') and not email_data.get('html_content') and compressed_rtf is not None:
            compressed = self._read_stream_from_msg(msg, '__substg1.0_1009')
            if compressed:
                try:
                    rtf_bytes = compressed_rtf.decompress(compressed)
                    plain = self._plain_text_from_rtf_bytes(rtf_bytes)
                    if plain:
                        email_data['text_content'] = plain
                except Exception as exc:
                    logger.warning("Compressed RTF fallback failed: %s", exc)

    @staticmethod
    def _plain_text_from_rtf_bytes(rtf_bytes: bytes) -> str:
        """Best-effort plain text from raw RTF bytes without RTFDE."""
        text = rtf_bytes.decode('latin-1', errors='replace')
        text = re.sub(r'\\par[d]?', '\n', text)
        text = re.sub(r'\\[a-z]+-?\d* ?', '', text)
        text = re.sub(r'[{}]', '', text)
        return re.sub(r'\n{3,}', '\n\n', text).strip()

    def _parse_msg_direct_ole(self, file_path: str) -> Optional[Dict[str, Any]]:
        """Last-resort parser using olefile when extract_msg cannot open the message."""
        if olefile is None:
            return None

        ole = None
        try:
            ole = olefile.OleFileIO(file_path)
            stream_map: Dict[str, bytes] = {}
            for parts in ole.listdir():
                name = '/'.join(parts)
                try:
                    stream_map[name] = ole.openstream(name).read()
                except Exception:
                    continue

            def find_stream(*suffixes: str) -> Optional[bytes]:
                for suffix in suffixes:
                    for name, data in stream_map.items():
                        if name.endswith(suffix):
                            return data
                return None

            subject_raw = find_stream('0037001F', '0037001E')
            body_raw = find_stream('1000001F', '1000001E')
            html_raw = find_stream('10130102')
            sender_raw = find_stream('0C1F001F', '0C1F001E', '5D01001F', '5D01001E')
            to_raw = find_stream('0E04001F', '0E04001E')

            text_content = self._decode_stream_text(body_raw)
            html_content = ''
            if html_raw:
                html_content = self._decode_stream_text(html_raw)

            if not text_content and not html_content and compressed_rtf is not None:
                compressed = find_stream('10090102')
                if compressed:
                    try:
                        text_content = self._plain_text_from_rtf_bytes(compressed_rtf.decompress(compressed))
                    except Exception:
                        pass

            sender_text = self._decode_stream_text(sender_raw)
            sender_name, sender_email = self._extract_email_from_string(sender_text)

            recipients: list = []
            to_text = self._decode_stream_text(to_raw)
            if to_text:
                for part in to_text.split(';'):
                    part = part.strip()
                    if part:
                        _, email = self._extract_email_from_string(part)
                        recipients.append(email or part)

            subject = self._decode_stream_text(subject_raw)

            if not any([subject, text_content, html_content, sender_email, recipients]):
                return None

            logger.info("Parsed .msg using direct OLE stream fallback")

            return {
                'success': True,
                'subject': subject,
                'sender_name': sender_name or '',
                'sender_email': sender_email or '',
                'sent_date': None,
                'received_date': None,
                'html_content': html_content,
                'text_content': text_content,
                'recipients': recipients,
                'attachments': [],
                'headers': {},
                'message_id': '',
                'file_path': file_path,
                'file_size': os.path.getsize(file_path),
            }
        except Exception as exc:
            logger.error("Direct OLE parse failed for %s: %s", file_path, exc)
            return None
        finally:
            if ole is not None:
                try:
                    ole.close()
                except Exception:
                    pass

    def _extract_email_payload(self, msg: Any, file_path: str) -> Dict[str, Any]:
        """Build the parsed email dict from an open extract_msg.Message (does not close msg)."""
        body_fields = {
            'text_content': self._safe_get(self._read_msg_property(msg, 'body', ''), ''),
            'html_content': self._safe_get(self._read_msg_property(msg, 'htmlBody', ''), ''),
        }
        self._fill_body_from_streams(msg, body_fields)

        email_data = {
            'success': True,
            'subject': self._safe_get(self._read_msg_property(msg, 'subject', ''), ''),
            'sender_name': '',
            'sender_email': '',
            'sent_date': self._safe_get(self._read_msg_property(msg, 'date'), None),
            'received_date': None,
            'html_content': body_fields['html_content'],
            'text_content': body_fields['text_content'],
            'recipients': [],
            'attachments': [],
            'headers': {},
            'message_id': self._safe_get(self._read_msg_property(msg, 'messageId', ''), ''),
            'file_path': file_path,
            'file_size': os.path.getsize(file_path),
        }

        sender_info = self._extract_sender_info(msg)
        email_data['sender_name'] = sender_info['name']
        email_data['sender_email'] = sender_info['email']

        email_data['recipients'] = self._extract_recipients(msg)

        if email_data['sent_date']:
            email_data['received_date'] = email_data['sent_date']

        try:
            email_data['attachments'] = self._extract_attachments(
                msg,
                body_text=email_data['text_content'],
                html_body=email_data['html_content'],
            )
        except Exception as exc:
            if self._is_recoverable_codec_failure(exc):
                logger.warning("Skipping attachments after codec error: %s", exc)
                email_data['attachments'] = []
            else:
                raise

        email_data['headers'] = self._extract_headers(msg)

        logger.info(f"Successfully parsed email: {email_data['subject']}")

        return email_data

    def parse_msg_file(self, file_path: str) -> Dict[str, Any]:
        """
        Parse a .msg file and extract all email data.
        
        Args:
            file_path: Path to the .msg file
        
        Returns:
            Dict containing parsed email data
        """
        try:
            logger.info(f"Parsing .msg file: {file_path}")
            
            if not os.path.exists(file_path):
                return {
                    'success': False,
                    'error': f'File not found: {file_path}'
                }
            
            # Parse with extract_msg: default path unchanged; fallbacks only on codec/decode failures
            # (covers both Message() and lazy property access such as body/htmlBody).
            last_codec_error: Optional[BaseException] = None

            for strategy_name, kwargs in self._message_open_strategies():
                msg = None
                try:
                    msg = extract_msg.Message(file_path, **kwargs)
                    email_data = self._extract_email_payload(msg, file_path)
                    if strategy_name != "default":
                        logger.info("Parsed .msg using fallback strategy: %s", strategy_name)
                    return email_data
                except RecursionError:
                    logger.error(
                        "Recursion depth exceeded parsing %s — likely deeply nested forwarded/embedded .msg",
                        file_path,
                    )
                    return {
                        'success': False,
                        'error': (
                            'This email contains deeply nested forwarded messages that exceed the parser depth limit. '
                            'Try saving or uploading the innermost message as its own .msg file.'
                        ),
                        'file_path': file_path,
                    }
                except Exception as e:
                    if self._is_recoverable_codec_failure(e):
                        last_codec_error = e
                        logger.warning(
                            "Parse attempt failed (%s), retrying if another strategy exists: %s",
                            strategy_name,
                            e,
                        )
                        continue
                    logger.error(
                        "Error parsing .msg file %s (strategy %s): %s",
                        file_path,
                        strategy_name,
                        str(e),
                    )
                    return {
                        'success': False,
                        'error': str(e),
                        'file_path': file_path,
                    }
                finally:
                    if msg is not None:
                        try:
                            msg.close()
                        except Exception:
                            pass

            if last_codec_error is not None:
                logger.error(
                    "All extract_msg strategies failed for %s: %s",
                    file_path,
                    last_codec_error,
                )
                direct_result = self._parse_msg_direct_ole(file_path)
                if direct_result:
                    return direct_result
                return {
                    'success': False,
                    'error': str(last_codec_error),
                    'file_path': file_path,
                }

            return {
                'success': False,
                'error': 'Failed to parse email file',
                'file_path': file_path,
            }

        except RecursionError:
            logger.error(
                "Recursion depth exceeded parsing %s — likely deeply nested forwarded/embedded .msg",
                file_path,
            )
            return {
                'success': False,
                'error': (
                    'This email contains deeply nested forwarded messages that exceed the parser depth limit. '
                    'Try saving or uploading the innermost message as its own .msg file.'
                ),
                'file_path': file_path,
            }

        except Exception as e:
            logger.error(f"Error parsing .msg file {file_path}: {str(e)}")
            return {
                'success': False,
                'error': str(e),
                'file_path': file_path
            }

    def parse_eml_file(self, file_path: str) -> Dict[str, Any]:
        """Parse a .eml (RFC 822) email file saved from Outlook or other clients."""
        try:
            logger.info(f"Parsing .eml file: {file_path}")

            if not os.path.exists(file_path):
                return {
                    'success': False,
                    'error': f'File not found: {file_path}'
                }

            if os.path.getsize(file_path) <= 0:
                return {
                    'success': False,
                    'error': 'Uploaded email file is empty.'
                }

            with open(file_path, 'rb') as handle:
                msg = email.message_from_binary_file(handle, policy=policy.default)

            sender_name, sender_email = self._extract_email_from_string(msg.get('From', '') or '')
            sent_date = self._parse_eml_date(msg.get('Date'))
            received_date = self._parse_eml_date(msg.get('Delivery-Date')) or sent_date

            html_content, text_content, attachments = self._extract_eml_body_and_attachments(msg)
            recipient_groups = self._extract_eml_recipient_groups(msg)

            email_data = {
                'success': True,
                'subject': self._safe_get(msg.get('Subject', ''), ''),
                'sender_name': sender_name or '',
                'sender_email': sender_email or '',
                'sent_date': sent_date,
                'received_date': received_date or sent_date,
                'html_content': html_content,
                'text_content': text_content,
                'recipients': recipient_groups['to'],
                'to_recipients': recipient_groups['to'],
                'cc_recipients': recipient_groups['cc'],
                'bcc_recipients': recipient_groups['bcc'],
                'attachments': attachments,
                'headers': self._extract_eml_headers(msg),
                'message_id': self._safe_get(msg.get('Message-ID', ''), ''),
                'file_path': file_path,
                'file_size': os.path.getsize(file_path),
            }

            logger.info(f"Successfully parsed EML email: {email_data['subject']}")
            return email_data

        except Exception as e:
            logger.error(f"Error parsing .eml file {file_path}: {str(e)}")
            return {
                'success': False,
                'error': str(e),
                'file_path': file_path
            }

    def _parse_eml_date(self, value: Any) -> Any:
        if not value:
            return None
        try:
            parsed = parsedate_to_datetime(str(value))
            if parsed.tzinfo is None:
                parsed = parsed.replace(tzinfo=timezone.utc)
            return parsed.isoformat()
        except Exception:
            return self._safe_get(value, None)

    def _extract_eml_recipient_groups(self, msg: Message) -> Dict[str, List[str]]:
        groups = {
            'to': self._addresses_from_header(msg.get('To', '')),
            'cc': self._addresses_from_header(msg.get('Cc', '')),
            'bcc': self._addresses_from_header(msg.get('Bcc', '')),
        }
        for key in groups:
            groups[key] = self._dedupe_preserve_order(groups[key])
        return groups

    def _addresses_from_header(self, header_value: str) -> List[str]:
        if not header_value:
            return []
        results: List[str] = []
        for _name, addr in getaddresses([header_value]):
            addr = (addr or '').strip()
            if addr:
                results.append(addr)
                continue
            name = (_name or '').strip()
            if name:
                results.append(name)
        return results

    def _extract_eml_headers(self, msg: Message) -> dict:
        headers = {}
        for key in ('From', 'To', 'Cc', 'Bcc', 'Subject', 'Date', 'Message-ID', 'Reply-To'):
            value = msg.get(key)
            if value:
                headers[key] = self._safe_get(value, '')
        return headers

    def _extract_eml_body_and_attachments(self, msg: Message) -> Tuple[str, str, list]:
        html_content = ''
        text_content = ''
        attachments = []

        if msg.is_multipart():
            for part in msg.walk():
                if part.get_content_maintype() == 'multipart':
                    continue
                disposition = str(part.get('Content-Disposition', '') or '').lower()
                filename = part.get_filename()
                content_type = part.get_content_type()

                if filename or 'attachment' in disposition:
                    attachment_data = self._build_eml_attachment(part, filename)
                    if attachment_data:
                        attachments.append(attachment_data)
                    continue

                if content_type == 'text/html' and not html_content:
                    html_content = self._safe_get(part.get_content(), '')
                elif content_type == 'text/plain' and not text_content:
                    text_content = self._safe_get(part.get_content(), '')
        else:
            content_type = msg.get_content_type()
            if content_type == 'text/html':
                html_content = self._safe_get(msg.get_content(), '')
            else:
                text_content = self._safe_get(msg.get_content(), '')

        return html_content, text_content, attachments

    def _build_eml_attachment(self, part: Message, filename: Optional[str]) -> Optional[dict]:
        try:
            payload = part.get_payload(decode=True) or b''
            filename = filename or part.get_filename() or 'attachment'
            content_id = self._safe_get(part.get('Content-ID', ''), '')
            disposition = str(part.get('Content-Disposition', '') or '').lower()
            is_inline = 'inline' in disposition or bool(content_id)

            attachment_data = {
                'filename': self._safe_get(filename, 'attachment'),
                'content_type': self._safe_get(part.get_content_type(), 'application/octet-stream'),
                'content_id': content_id,
                'is_inline': is_inline,
                'size': len(payload),
                'data': None,
            }

            if payload and len(payload) < 31457280:
                attachment_data['data'] = base64.b64encode(payload).decode('ascii')

            return attachment_data
        except Exception as e:
            logger.warning(f"Error processing EML attachment: {str(e)}")
            return None

    def _dedupe_preserve_order(self, items: List[str]) -> List[str]:
        seen = set()
        deduped: List[str] = []
        for item in items:
            key = item.strip().lower()
            if not key or key in seen:
                continue
            seen.add(key)
            deduped.append(item.strip())
        return deduped
    
    def _safe_get(self, value: Any, default: Any = None, _depth: int = 0) -> Any:
        """Safely get value and convert to JSON-serializable format."""
        if _depth > 50:
            return default

        if value is None:
            return default
        
        if isinstance(value, str):
            return value
        elif isinstance(value, bytes):
            try:
                return value.decode('utf-8', errors='ignore')
            except Exception:
                return str(value)
        elif isinstance(value, datetime):
            # Ensure datetime is timezone-aware before converting to ISO
            # If naive (no timezone), assume UTC to preserve the exact time
            if value.tzinfo is None:
                # Naive datetime - assume UTC to preserve original time
                value = value.replace(tzinfo=timezone.utc)
            return value.isoformat()
        elif isinstance(value, (int, float, bool)):
            return value
        elif isinstance(value, (list, tuple)):
            return [self._safe_get(item, default=None, _depth=_depth + 1) for item in value]
        elif isinstance(value, dict):
            return {
                str(k): self._safe_get(v, default=None, _depth=_depth + 1)
                for k, v in value.items()
            }
        else:
            try:
                return str(value)
            except Exception:
                return default
    
    def _extract_sender_info(self, msg) -> Dict[str, str]:
        """Extract sender name and email from message."""
        sender_fields = [
            'sender', 'from', 'senderEmail', 'senderEmailAddress', 'senderName',
            'from_', 'fromAddress', 'fromAddresses', 'fromEmail', 'fromEmailAddress',
            'fromName', 'fromDisplayName', 'fromDisplay', 'fromUser', 'fromUserEmail',
            'senderAddress', 'senderAddresses', 'senderDisplayName', 'senderDisplay',
            'senderUser', 'senderUserEmail', 'senderEmailAddresses', 'senderEmails'
        ]
        
        sender_info = None
        for field in sender_fields:
            value = self._read_msg_property(msg, field)
            if value:
                sender_info = value
                break
        
        if not sender_info:
            return {'name': '', 'email': ''}
        
        # Parse sender information
        name, email = self._extract_email_from_string(str(sender_info))
        return {'name': name or '', 'email': email or ''}
    
    def _extract_recipients(self, msg) -> list:
        """Extract recipient information from message."""
        recipient_fields = [
            'to', 'recipients', 'toRecipients', 'toAddress', 'toAddresses',
            'toEmail', 'toEmails', 'toEmailAddress', 'toEmailAddresses',
            'toName', 'toNames', 'toDisplayName', 'toDisplayNames',
            'recipient', 'recipientAddress', 'recipientAddresses',
            'recipientEmail', 'recipientEmails', 'recipientEmailAddress',
            'recipientEmailAddresses', 'recipientName', 'recipientNames'
        ]
        
        recipients = []
        for field in recipient_fields:
            value = self._read_msg_property(msg, field)
            if not value:
                continue
            try:
                if isinstance(value, str):
                    recipients.extend([r.strip() for r in value.split(',')])
                elif isinstance(value, list):
                    recipients.extend([str(r).strip() for r in value])
                elif hasattr(value, '__iter__') and not isinstance(value, (str, bytes)):
                    recipients.extend([str(r).strip() for r in value])
            except Exception:
                continue
        
        # Remove duplicates and empty values
        recipients = list(set([r for r in recipients if r]))
        
        # Extract email addresses from recipient strings
        processed_recipients = []
        for recipient in recipients:
            name, email = self._extract_email_from_string(recipient)
            if email:
                processed_recipients.append(email)
            elif name:
                processed_recipients.append(name)
        
        return processed_recipients
    
    def _extract_email_from_string(self, text: str) -> Tuple[Optional[str], Optional[str]]:
        """Extract email address from string that might contain name and email."""
        if not text:
            return None, None
        
        text = str(text).strip()
        
        # Format: "Name <email@domain.com>"
        if '<' in text and '>' in text:
            try:
                email_part = text.split('<')[1].split('>')[0].strip()
                name_part = text.split('<')[0].strip()
                
                # Validate email
                if '@' in email_part and '.' in email_part.split('@')[1]:
                    return name_part if name_part else None, email_part
            except Exception:
                pass
        
        # Format: "email@domain.com" or "Name email@domain.com"
        if '@' in text:
            parts = text.split()
            email_part = None
            name_parts = []
            
            for part in parts:
                if '@' in part and '.' in part.split('@')[1]:
                    email_part = part
                else:
                    name_parts.append(part)
            
            if email_part:
                name_part = ' '.join(name_parts) if name_parts else None
                return name_part, email_part
        
        # No valid email found
        return text if text else None, None
    
    def _extract_attachments(
        self,
        msg,
        body_text: str = '',
        html_body: str = '',
    ) -> list:
        """Extract attachment information from message."""
        attachments = []

        # Use pre-extracted body strings (from parse_msg_file) for cid: inline detection
        body = body_text or ''
        html = html_body or ''
        combined_body = f"{body}{html}".lower()
        
        try:
            for attachment in msg.attachments:
                try:
                    content_id = self._safe_get(getattr(attachment, 'contentId', ''), '')
                    
                    # Only mark as inline if:
                    # 1. It has a content_id AND
                    # 2. The body references it with cid:
                    # 3. OR it's an image with content_id (common for inline images)
                    is_inline = False
                    if content_id:
                        # Check if body references this content_id
                        cid_ref = f"cid:{content_id.strip('<>')}"
                        if cid_ref.lower() in combined_body:
                            is_inline = True
                    
                    attachment_data = {
                        'filename': self._safe_get(attachment.longFilename or attachment.shortFilename, 'Unknown'),
                        'content_type': self._safe_get(getattr(attachment, 'contentType', 'application/octet-stream'), 'application/octet-stream'),
                        'content_id': content_id,
                        'is_inline': is_inline,
                        'size': len(attachment.data) if attachment.data else 0,
                        'data': None
                    }
                    
                    # Only include data if it's not too large (30MB limit - matches upload limit)
                    if attachment.data and len(attachment.data) < 31457280:  # 30MB limit (30 * 1024 * 1024)
                        try:
                            # Base64 encode binary data for safe JSON transmission
                            # This preserves binary data integrity (PDFs, images, etc.)
                            if isinstance(attachment.data, bytes):
                                attachment_data['data'] = base64.b64encode(attachment.data).decode('ascii')
                            else:
                                # If it's already a string, try to encode it
                                attachment_data['data'] = base64.b64encode(attachment.data.encode('latin-1')).decode('ascii')
                            logger.debug(f"Encoded attachment {attachment_data['filename']}: {len(attachment_data['data'])} chars (original: {len(attachment.data)} bytes)")
                        except Exception as e:
                            logger.error(f"Failed to encode attachment {attachment_data['filename']}: {str(e)}")
                            attachment_data['data'] = None
                    
                    attachments.append(attachment_data)
                    
                except Exception as e:
                    logger.warning(f"Error processing attachment: {str(e)}")
                    # Add basic attachment info if detailed processing fails
                    attachments.append({
                        'filename': 'Unknown',
                        'content_type': 'application/octet-stream',
                        'content_id': '',
                        'is_inline': False,
                        'size': 0,
                        'data': None
                    })
        except Exception as e:
            logger.warning(f"Error extracting attachments: {str(e)}")
        
        return attachments
    
    def _extract_headers(self, msg) -> dict:
        """Extract email headers from message."""
        headers = {}
        
        try:
            if hasattr(msg, 'headers') and msg.headers:
                if isinstance(msg.headers, dict):
                    headers = {k: self._safe_get(v) for k, v in msg.headers.items()}
                elif isinstance(msg.headers, str):
                    # Parse headers manually
                    for line in msg.headers.split('\n'):
                        line = line.strip()
                        if ':' in line:
                            header_name, header_value = line.split(':', 1)
                            headers[header_name.strip()] = header_value.strip()
        except Exception as e:
            logger.warning(f"Error extracting headers: {str(e)}")
        
        return headers
    
    def test_parsing(self, file_path: str) -> Dict[str, Any]:
        """Test parsing on a specific file and return debug information."""
        try:
            logger.info(f"Testing parsing for: {file_path}")

            result = self.parse_msg_file(file_path)
            parse_ok = result.get('success', False)

            return {
                'success': parse_ok,
                'file_path': file_path,
                'file_exists': os.path.exists(file_path),
                'file_size': os.path.getsize(file_path) if os.path.exists(file_path) else 0,
                'parsed_data': result,
                'extract_msg_available': 'extract_msg' in sys.modules,
            }

        except Exception as e:
            logger.error(f"Error in test parsing: {str(e)}")
            return {
                'success': False,
                'error': str(e),
                'file_path': file_path,
                'file_exists': os.path.exists(file_path),
            }
