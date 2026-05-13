<?php

namespace App\Infrastructure\Observers;

use App\Infrastructure\Models\Contact;

class ContactObserver
{
    public function saving(Contact $contact): void
    {
        if ($contact->isDirty('phone')) {
            $contact->phone = preg_replace('/\D/', '', $contact->phone);
        }
    }
}
