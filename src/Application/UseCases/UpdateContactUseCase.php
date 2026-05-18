<?php

namespace Application\UseCases;

use Domain\Entities\Contact;
use Domain\Repositories\ContactRepositoryInterface;
use Domain\ValueObjects\Email;
use Domain\ValueObjects\Phone;

class UpdateContactUseCase
{
    public function __construct(
        private readonly ContactRepositoryInterface $repository,
    ) {
    }

    public function execute(int $id, string $name, string $email, string $phone): ?Contact
    {
        $contact = $this->repository->findById($id);

        if ($contact === null) {
            return null;
        }

        if ($name !== null) {
            $contact->updateName($name);
        }

        if ($email !== null) {
            $contact->updateEmail(new Email($email));
        }

        if ($phone !== null) {
            $contact->changePhone(new Phone($phone));
        }

        $this->repository->save($contact);

        return $contact;
    }
}
