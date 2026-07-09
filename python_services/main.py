#!/usr/bin/env python3
"""
Unified Python Services for Migration Manager

This service provides:
- PDF processing and conversion
- Email parsing (.msg files)
- Email analysis and categorization
- Document conversion (DOCX/DOC to PDF)
- Email rendering and enhancement

Author: Migration Manager Team
Version: 1.0.0
"""

import sys

# Higher limit for deeply forwarded .msg chains (extract_msg can recurse on embedded messages)
sys.setrecursionlimit(5000)

import logging
import time
from pathlib import Path
from typing import Dict, Any

from fastapi import FastAPI, UploadFile, File, HTTPException, Request
from fastapi.responses import JSONResponse, FileResponse
from fastapi.middleware.cors import CORSMiddleware
import uvicorn

# Add services to path
sys.path.insert(0, str(Path(__file__).parent))

from services.pdf_service import PDFService
from services.email_parser_service import EmailParserService
from services.email_analyzer_service import EmailAnalyzerService
from services.email_renderer_service import EmailRendererService
from services.docx_converter_service import DocxConverterService
from utils.logger import setup_logger
from utils.validators import validate_file_type, validate_file_size

# Setup logging
logger = setup_logger(__name__)

# Global service instances (initialized by create_app)
pdf_service = None
email_parser = None
email_analyzer = None
email_renderer = None
docx_converter = None


def create_app() -> FastAPI:
    """
    Factory function to create and configure the FastAPI application.
    This prevents double initialization when uvicorn reloads the module.
    """
    global pdf_service, email_parser, email_analyzer, email_renderer, docx_converter
    
    # Initialize FastAPI app
    app = FastAPI(
        title="Migration Manager Python Services",
        description="Unified Python services for PDF processing, email parsing, and document conversion",
        version="1.0.0"
    )

    # CORS middleware
    app.add_middleware(
        CORSMiddleware,
        allow_origins=["*"],  # Configure based on your needs
        allow_credentials=True,
        allow_methods=["*"],
        allow_headers=["*"],
    )

    # Initialize services (only once via factory)
    pdf_service = PDFService()
    email_parser = EmailParserService()
    email_analyzer = EmailAnalyzerService()
    email_renderer = EmailRendererService()
    docx_converter = DocxConverterService()
    
    return app


# Create app instance
# This will be called once by module import, and routes will be registered
app = create_app()


# ============================================================================
# Health Check Endpoints
# ============================================================================

@app.get("/")
async def root():
    """Root endpoint with service information."""
    return {
        "service": "Migration Manager Python Services",
        "version": "1.0.0",
        "status": "running",
        "endpoints": {
            "pdf": "/pdf/*",
            "email": "/email/*",
            "health": "/health"
        }
    }


@app.get("/health")
async def health_check():
    """Health check endpoint."""
    # Check if LibreOffice is available for DOCX conversion
    libreoffice_available = docx_converter.is_libreoffice_available()
    
    # Determine converter status and method
    converter_status = "unavailable"
    converter_method = None
    converter_message = None
    
    if docx_converter.conversion_method == 'disabled':
        converter_status = "disabled"
        converter_message = "DOCX conversion is disabled"
    elif libreoffice_available:
        converter_status = "ready"
        converter_method = "libreoffice"
    elif docx_converter.conversion_method == 'libreoffice':
        converter_status = "unavailable"
        converter_message = "LibreOffice not found"
    elif docx_converter.conversion_method == 'docx2pdf':
        from services.docx_converter_service import DOCX2PDF_AVAILABLE
        if DOCX2PDF_AVAILABLE:
            converter_status = "ready"
            converter_method = "docx2pdf"
            converter_message = "Using docx2pdf (requires Microsoft Word)"
        else:
            converter_status = "unavailable"
            converter_message = "docx2pdf not available"
    else:  # auto mode
        from services.docx_converter_service import DOCX2PDF_AVAILABLE
        if DOCX2PDF_AVAILABLE:
            converter_status = "limited"
            converter_method = "docx2pdf"
            converter_message = "LibreOffice not found, using docx2pdf fallback"
        else:
            converter_status = "unavailable"
            converter_message = "No conversion method available"
    
    return {
        "status": "healthy",
        "services": {
            "pdf_service": "ready",
            "email_parser": "ready",
            "email_analyzer": "ready",
            "email_renderer": "ready",
            "docx_converter": converter_status
        },
        "docx_converter_details": {
            "status": converter_status,
            "method": converter_method,
            "message": converter_message,
            "libreoffice_path": docx_converter.libreoffice_path,
            "configured_method": docx_converter.conversion_method
        }
    }


# ============================================================================
# PDF Service Endpoints
# ============================================================================

@app.post("/pdf/convert-to-images")
async def convert_pdf_to_images(file: UploadFile = File(...)):
    """Convert PDF pages to images."""
    try:
        logger.info(f"Converting PDF to images: {file.filename}")
        
        # Validate file
        if not validate_file_type(file.filename, ['.pdf']):
            raise HTTPException(status_code=400, detail="Invalid file type. Only PDF files are allowed.")
        
        # Read file content
        content = await file.read()
        
        # Convert to images
        result = pdf_service.convert_to_images(content, file.filename)
        
        return JSONResponse(content=result)
        
    except Exception as e:
        logger.error(f"Error converting PDF to images: {str(e)}")
        raise HTTPException(status_code=500, detail=str(e))


@app.post("/pdf/merge")
async def merge_pdfs(files: list[UploadFile] = File(...)):
    """Merge multiple PDF files."""
    try:
        logger.info(f"Merging {len(files)} PDF files")
        
        # Validate files
        for file in files:
            if not validate_file_type(file.filename, ['.pdf']):
                raise HTTPException(status_code=400, detail=f"Invalid file type: {file.filename}")
        
        # Read file contents
        pdf_contents = [await file.read() for file in files]
        
        # Merge PDFs
        result = pdf_service.merge_pdfs(pdf_contents)
        
        return JSONResponse(content=result)
        
    except Exception as e:
        logger.error(f"Error merging PDFs: {str(e)}")
        raise HTTPException(status_code=500, detail=str(e))


@app.post("/convert_page")
async def convert_page(request: Request):
    """Convert a single PDF page to image."""
    try:
        data = await request.json()
        file_path = data.get('file_path')
        page_number = data.get('page_number', 1)
        resolution = data.get('resolution', 150)
        
        if not file_path:
            raise HTTPException(status_code=400, detail="file_path is required")
        
        logger.info(f"Converting page {page_number} of {file_path}")
        
        result = pdf_service.convert_page_to_image(file_path, page_number, resolution)
        
        if not result.get('success'):
            raise HTTPException(status_code=500, detail=result.get('error', 'Conversion failed'))
        
        return JSONResponse(content=result)
        
    except HTTPException:
        raise
    except Exception as e:
        logger.error(f"Error converting page: {str(e)}")
        raise HTTPException(status_code=500, detail=str(e))


@app.post("/pdf_info")
async def get_pdf_info(request: Request):
    """Get PDF information (page count, metadata, etc.)."""
    try:
        data = await request.json()
        file_path = data.get('file_path')
        
        if not file_path:
            raise HTTPException(status_code=400, detail="file_path is required")
        
        logger.info(f"Getting PDF info for: {file_path}")
        
        result = pdf_service.get_pdf_info(file_path)
        
        if not result.get('success'):
            raise HTTPException(status_code=500, detail=result.get('error', 'Failed to get PDF info'))
        
        return JSONResponse(content=result)
        
    except HTTPException:
        raise
    except Exception as e:
        logger.error(f"Error getting PDF info: {str(e)}")
        raise HTTPException(status_code=500, detail=str(e))


@app.post("/validate_pdf")
async def validate_pdf(request: Request):
    """Validate a PDF file."""
    try:
        data = await request.json()
        file_path = data.get('file_path')
        
        if not file_path:
            raise HTTPException(status_code=400, detail="file_path is required")
        
        logger.info(f"Validating PDF: {file_path}")
        
        result = pdf_service.validate_pdf(file_path)
        
        return JSONResponse(content=result)
        
    except Exception as e:
        logger.error(f"Error validating PDF: {str(e)}")
        raise HTTPException(status_code=500, detail=str(e))


@app.post("/normalize_pdf")
async def normalize_pdf(request: Request):
    """Normalize PDF for better compatibility."""
    try:
        data = await request.json()
        input_path = data.get('input_path')
        output_path = data.get('output_path')
        
        if not input_path or not output_path:
            raise HTTPException(status_code=400, detail="input_path and output_path are required")
        
        logger.info(f"Normalizing PDF: {input_path} -> {output_path}")
        
        result = pdf_service.normalize_pdf(input_path, output_path)
        
        if not result.get('success'):
            raise HTTPException(status_code=500, detail=result.get('error', 'Normalization failed'))
        
        return JSONResponse(content=result)
        
    except HTTPException:
        raise
    except Exception as e:
        logger.error(f"Error normalizing PDF: {str(e)}")
        raise HTTPException(status_code=500, detail=str(e))


@app.post("/add_signatures")
async def add_signatures(request: Request):
    """Add signatures to PDF at specified positions."""
    try:
        data = await request.json()
        input_path = data.get('input_path')
        output_path = data.get('output_path')
        signatures = data.get('signatures', [])
        
        if not input_path or not output_path:
            raise HTTPException(status_code=400, detail="input_path and output_path are required")
        
        logger.info(f"Adding {len(signatures)} signatures to PDF")
        
        result = pdf_service.add_signatures_to_pdf(input_path, output_path, signatures)
        
        if not result.get('success'):
            raise HTTPException(status_code=500, detail=result.get('error', 'Failed to add signatures'))
        
        return JSONResponse(content=result)
        
    except HTTPException:
        raise
    except Exception as e:
        logger.error(f"Error adding signatures: {str(e)}")
        raise HTTPException(status_code=500, detail=str(e))


@app.post("/batch_convert")
async def batch_convert_pages(request: Request):
    """Convert multiple PDF pages to images in batch."""
    try:
        data = await request.json()
        file_path = data.get('file_path')
        pages = data.get('pages', [])
        resolution = data.get('resolution', 150)
        
        if not file_path or not pages:
            raise HTTPException(status_code=400, detail="file_path and pages are required")
        
        logger.info(f"Batch converting {len(pages)} pages")
        
        result = pdf_service.batch_convert_pages(file_path, pages, resolution)
        
        if not result.get('success'):
            raise HTTPException(status_code=500, detail=result.get('error', 'Batch conversion failed'))
        
        return JSONResponse(content=result)
        
    except HTTPException:
        raise
    except Exception as e:
        logger.error(f"Error batch converting: {str(e)}")
        raise HTTPException(status_code=500, detail=str(e))


# ============================================================================
# DOCX Converter Endpoints
# ============================================================================

@app.post("/convert")
async def convert_docx_to_pdf(file: UploadFile = File(...)):
    """Convert DOCX/DOC file to PDF."""
    try:
        logger.info(f"Converting document: {file.filename}")
        
        # Validate file
        if not validate_file_type(file.filename, ['.doc', '.docx']):
            raise HTTPException(status_code=400, detail="Invalid file type. Only DOC/DOCX files are allowed.")
        
        # Read file content
        content = await file.read()
        
        # Convert to PDF
        result = docx_converter.convert_to_pdf(content, file.filename)
        
        if not result.get('success'):
            raise HTTPException(status_code=500, detail=result.get('error', 'Conversion failed'))
        
        return JSONResponse(content=result)
        
    except HTTPException:
        raise
    except Exception as e:
        logger.error(f"Error converting DOCX to PDF: {str(e)}")
        raise HTTPException(status_code=500, detail=str(e))


@app.post("/convert-json")
async def convert_docx_to_pdf_json(file: UploadFile = File(...)):
    """Convert DOCX/DOC file to PDF (JSON endpoint - alias for /convert)."""
    return await convert_docx_to_pdf(file)


# ============================================================================
# Email Service Endpoints
# ============================================================================

ALLOWED_EMAIL_EXTENSIONS = ['.msg', '.eml']


@app.post("/email/parse")
async def parse_email(file: UploadFile = File(...)):
    """Parse .msg or .eml file and extract email data."""
    temp_path = None
    try:
        logger.info(f"Parsing email file: {file.filename}")

        # Validate file
        if not validate_file_type(file.filename, ALLOWED_EMAIL_EXTENSIONS):
            raise HTTPException(
                status_code=400,
                detail="Invalid file type. Only .msg and .eml files are allowed."
            )

        # Unique temp filename to avoid collisions when concurrent uploads share the same name
        temp_filename = f"{int(time.time() * 1000)}_{file.filename}"
        temp_path = Path(f"temp/{temp_filename}")
        temp_path.parent.mkdir(exist_ok=True)

        content = await file.read()
        temp_path.write_bytes(content)

        # Parse email
        result = email_parser.parse_email_file(str(temp_path))

        return JSONResponse(content=result)

    except Exception as e:
        logger.error(f"Error parsing email: {str(e)}")
        raise HTTPException(status_code=500, detail=str(e))

    finally:
        # Always clean up temp file regardless of success or failure
        if temp_path and temp_path.exists():
            try:
                temp_path.unlink()
            except Exception as cleanup_err:
                logger.warning(f"Could not delete temp file {temp_path}: {cleanup_err}")


@app.post("/email/analyze")
async def analyze_email(request: Request):
    """Analyze email content for categorization, priority, sentiment, etc."""
    try:
        email_data = await request.json()
        logger.info(f"Analyzing email: {email_data.get('subject', 'No subject')}")
        
        # Analyze email
        result = email_analyzer.analyze_content(email_data)
        
        return JSONResponse(content=result)
        
    except Exception as e:
        logger.error(f"Error analyzing email: {str(e)}")
        raise HTTPException(status_code=500, detail=str(e))


@app.post("/email/render")
async def render_email(request: Request):
    """Render email with enhanced HTML and styling."""
    try:
        email_data = await request.json()
        logger.info(f"Rendering email: {email_data.get('subject', 'No subject')}")
        
        # Render email
        result = email_renderer.render_email(email_data)
        
        return JSONResponse(content=result)
        
    except Exception as e:
        logger.error(f"Error rendering email: {str(e)}")
        raise HTTPException(status_code=500, detail=str(e))


@app.post("/email/parse-analyze-render")
async def parse_analyze_render_email(file: UploadFile = File(...)):
    """
    Complete email processing pipeline:
    1. Parse .msg or .eml file
    2. Analyze content
    3. Render enhanced HTML
    """
    temp_path = None
    try:
        logger.info(f"Processing email file: {file.filename}")
        
        # Validate file
        if not validate_file_type(file.filename, ALLOWED_EMAIL_EXTENSIONS):
            raise HTTPException(
                status_code=400,
                detail="Invalid file type. Only .msg and .eml files are allowed."
            )
        
        # Save file temporarily with unique name to avoid conflicts
        temp_filename = f"{int(time.time() * 1000)}_{file.filename}"
        temp_path = Path(f"temp/{temp_filename}")
        temp_path.parent.mkdir(exist_ok=True)
        
        content = await file.read()
        temp_path.write_bytes(content)
        
        # Step 1: Parse email
        parsed_data = email_parser.parse_email_file(str(temp_path))
        
        if 'error' in parsed_data:
            return JSONResponse(content=parsed_data, status_code=500)
        
        # Step 2: Analyze email
        analysis = email_analyzer.analyze_content(parsed_data)
        
        # Step 3: Render email
        rendering = email_renderer.render_email(parsed_data)
        
        # Combine results
        result = {
            **parsed_data,
            'analysis': analysis,
            'rendering': rendering,
            'processing_status': 'success'
        }
        
        # Clean up - retry mechanism for Windows file locking
        if temp_path and temp_path.exists():
            for attempt in range(3):
                try:
                    temp_path.unlink()
                    break
                except PermissionError as pe:
                    if attempt < 2:
                        time.sleep(0.1)  # Wait 100ms before retry
                    else:
                        logger.warning(f"Could not delete temp file {temp_path}: {str(pe)}")
                except Exception as cleanup_error:
                    logger.warning(f"Error during cleanup of {temp_path}: {str(cleanup_error)}")
        
        return JSONResponse(content=result)
        
    except Exception as e:
        logger.error(f"Error in email processing pipeline: {str(e)}")
        # Attempt cleanup on error
        if temp_path and temp_path.exists():
            try:
                temp_path.unlink()
            except Exception as cleanup_err:
                logger.warning(f"Could not clean up temp file on error: {temp_path}: {cleanup_err}")
        raise HTTPException(status_code=500, detail=str(e))


# ============================================================================
# Error Handlers
# ============================================================================

@app.exception_handler(HTTPException)
async def http_exception_handler(request: Request, exc: HTTPException):
    """Handle HTTP exceptions."""
    logger.error(f"HTTP {exc.status_code}: {exc.detail}")
    return JSONResponse(
        status_code=exc.status_code,
        content={"error": exc.detail, "status_code": exc.status_code}
    )


@app.exception_handler(Exception)
async def general_exception_handler(request: Request, exc: Exception):
    """Handle general exceptions."""
    logger.error(f"Unexpected error: {str(exc)}", exc_info=True)
    return JSONResponse(
        status_code=500,
        content={"error": "Internal server error", "detail": str(exc)}
    )


# ============================================================================
# Main Entry Point
# ============================================================================

if __name__ == "__main__":
    import argparse
    
    parser = argparse.ArgumentParser(description="Migration Manager Python Services")
    parser.add_argument("--host", default="127.0.0.1", help="Host to bind to")
    parser.add_argument("--port", type=int, default=5001, help="Port to bind to")
    parser.add_argument("--reload", action="store_true", help="Enable auto-reload")
    
    args = parser.parse_args()
    
    logger.info(f"Starting Migration Manager Python Services on {args.host}:{args.port}")
    
    # Pass app object directly to prevent double initialization
    # Note: reload mode will still cause re-imports, but that's expected behavior
    uvicorn.run(
        app,
        host=args.host,
        port=args.port,
        reload=args.reload,
        log_level="info"
    )

