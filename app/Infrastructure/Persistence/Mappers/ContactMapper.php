<?php

namespace App\Infrastructure\Persistence\Mappers;

use App\Infrastructure\Models\Contact as ContactModel;
use Domain\Entities\Contact;
use Domain\Enums\ContactStatus;
use Domain\ValueObjects\Email;
use Domain\ValueObjects\Phone;
use Domain\ValueObjects\Score;

final readonly class ContactMapper
{
    public function toDomain(ContactModel $model): Contact
    {
        return Contact::reconstitute(
            $model->id,
            $model->name,
            new Email($model->email),
            new Phone($model->phone),
            new Score($model->score),
            $model->status ?? ContactStatus::Pending,
            $model->processed_at ? \DateTimeImmutable::createFromMutable($model->processed_at) : null,
            \DateTimeImmutable::createFromMutable($model->created_at),
            $model->updated_at ? \DateTimeImmutable::createFromMutable($model->updated_at) : null,
            $model->deleted_at ? \DateTimeImmutable::createFromMutable($model->deleted_at) : null
        );
    }

    public function toEloquent(Contact $contact): ContactModel
    {
        $model = new ContactModel();
        $model->name = $contact->name();
        $model->email = $contact->email()->value;
        $model->phone = $contact->phone()->value;
        $model->score = $contact->score()->value;
        $model->status = $contact->status()->value;

        if ($contact->processedAt() !== null) {
            $model->processed_at = $contact->processedAt()->format('Y-m-d H:i:s');
        }

        return $model;
    }
}
