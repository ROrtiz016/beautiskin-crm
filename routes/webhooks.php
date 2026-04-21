<?php

use App\Http\Controllers\CommunicationWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('webhooks/twilio/sms', [CommunicationWebhookController::class, 'twilioInboundSms'])
    ->name('webhooks.twilio.sms');

Route::post('webhooks/sendgrid/inbound', [CommunicationWebhookController::class, 'sendgridInbound'])
    ->name('webhooks.sendgrid.inbound');
