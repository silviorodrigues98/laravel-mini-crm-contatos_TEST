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

    public function execute(int $id, string $name, string $email, string $phone): Contact
    {
        $contact = $this->repository->findById($id);

        if ($contact === null) {
            throw new \DomainException("Contact with ID {$id} not found.");
        }

        $contact->updateName($name);
        $contact->updateEmail(new Email($email));
        $contact->changePhone(new Phone($phone));

        $this->repository->save($contact);

        return $contact;
    }
}
