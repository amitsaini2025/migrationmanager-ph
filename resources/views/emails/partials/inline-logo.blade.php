@php
    $appointmentEmailLogoPath = public_path('img/logo.png');
    $appointmentEmailLogoData = '';
    if (file_exists($appointmentEmailLogoPath)) {
        $appointmentEmailLogoData = 'data:image/png;base64,' . base64_encode(file_get_contents($appointmentEmailLogoPath));
    }
    $appointmentEmailLogoStyle = $style ?? 'display:block; border:0; outline:none; text-decoration:none; max-width:260px; width:auto; height:auto;';
@endphp
@if($appointmentEmailLogoData)
    <img src="{{ $appointmentEmailLogoData }}" alt="Bansal Immigration Consultants" style="{{ $appointmentEmailLogoStyle }}" />
@endif
