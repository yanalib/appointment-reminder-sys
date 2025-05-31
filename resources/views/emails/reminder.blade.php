<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Appointment Reminder</title>
</head>
<body>
    <p>Dear {{ $client->first_name }} {{ $client->last_name }},</p>

    <p>This is a reminder for your upcoming appointment:</p>

    <ul>
        <li><strong>Title:</strong> {{ $appointment->title }}</li>
        <li><strong>Date:</strong> {{ $appointment->start_datetime->format('l, F j, Y') }}</li>
        <li><strong>Time:</strong> {{ $appointment->start_datetime->format('g:i A') }}</li>
    </ul>

    @if($appointment->description)
        <p><strong>Details:</strong> {{ $appointment->description }}</p>
    @endif

    <p>If you need to reschedule, please contact us as soon as possible.</p>

    <p>Best regards,<br>
    Your Appointment Team</p>
</body>
</html>