<?php

namespace App\Mail;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Confirmation mail sent the moment a table is requested. In development it
 * lands in Mailpit — open http://localhost:8025 to read it.
 */
class BookingReceived extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Booking $booking) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Your table at {$this->booking->restaurant->name} — {$this->booking->reference}",
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.bookings.received');
    }
}
