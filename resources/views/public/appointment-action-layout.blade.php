<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>{{ $title ?? 'Appointment' }} - Bansal Immigration</title>
  <style>
    * { box-sizing: border-box; }
    body { margin: 0; font-family: Arial, sans-serif; background: #f4f4f4; color: #333; }
    .wrap { max-width: 560px; margin: 40px auto; background: #fff; border-radius: 8px; box-shadow: 0 2px 12px rgba(0,0,0,.08); overflow: hidden; }
    .header { background: #1c2a3a; color: #fff; padding: 24px; text-align: center; }
    .header h1 { margin: 0 0 6px; font-size: 20px; }
    .header p { margin: 0; font-size: 14px; color: #c8d4df; }
    .body { padding: 24px; }
    .detail { margin-bottom: 10px; font-size: 14px; line-height: 1.5; }
    .detail strong { display: inline-block; min-width: 90px; color: #555; }
    .msg { padding: 14px; border-radius: 6px; margin-bottom: 16px; font-size: 14px; }
    .msg.error { background: #fdecea; color: #b71c1c; border: 1px solid #f5c6cb; }
    .msg.success { background: #e8f5e9; color: #1b5e20; border: 1px solid #c8e6c9; }
    .msg.info { background: #eef6fb; color: #1a4a6a; border: 1px solid #bee5eb; }
    label { display: block; font-size: 14px; font-weight: bold; color: #555; margin-bottom: 8px; }
    textarea, input[type="date"], select { width: 100%; padding: 10px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px; margin-bottom: 14px; }
    .actions { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 8px; }
    button, .btn { padding: 12px 18px; border-radius: 6px; font-size: 15px; font-weight: bold; cursor: pointer; text-decoration: none; display: inline-block; }
    .btn-primary { background: #1c2a3a; color: #fff; border: none; }
    .btn-confirm { background: #27ae60; color: #fff; border: none; }
    .btn-cancel { background: #fff; color: #e74c3c; border: 2px solid #e74c3c; }
    .slot { display: block; width: 100%; margin-bottom: 8px; padding: 10px; border: 1px solid #d1d5db; border-radius: 6px; background: #fff; text-align: left; }
    .slot.selected { border-color: #27ae60; background: #e8f8ef; }
    .footer { text-align: center; padding: 16px; font-size: 12px; color: #888; }
    .errors { color: #b71c1c; font-size: 13px; margin-bottom: 12px; }
    .appt-details { margin-top: 16px; border-collapse: collapse; }
    .appt-details-head { background: #1c2a3a; color: #fff; font-size: 15px; font-weight: bold; padding: 12px 16px; }
    .appt-details-body { border: 2px solid #f5a623; border-top: none; }
    .appt-details-body table { border-collapse: collapse; }
    .appt-details-body tr { border-bottom: 1px solid #e8e8e8; }
    .appt-details-body tr:last-child { border-bottom: none; }
    .appt-details-body td { padding: 10px 12px; font-size: 14px; vertical-align: top; }
    .appt-details-label { font-weight: bold; color: #555; width: 120px; white-space: nowrap; }
    .appt-type-pill { display: inline-block; background: #f5a623; color: #fff; font-size: 12px; font-weight: bold; padding: 4px 12px; border-radius: 20px; }
  </style>
</head>
<body>
<div class="wrap">
  <div class="header">
    <h1>Bansal Immigration</h1>
    <p>{{ $heading ?? $title ?? 'Appointment' }}</p>
  </div>
  <div class="body">
    @yield('content')
  </div>
  <div class="footer">Registered Migration Agents</div>
</div>
@yield('scripts')
</body>
</html>
