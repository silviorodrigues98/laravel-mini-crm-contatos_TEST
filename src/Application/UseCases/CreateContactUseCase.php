<?php

namespace Application\UseCases;

use Domain\Entities\Contact;
use Domain\Repositories\ContactRepositoryInterface;
use Domain\ValueObjects\Email;
use Domain\ValueObjects\Phone;

class CreateContactUseCase
{
    public function __construct(
        private readonly ContactRepositoryInterface $repository,
    ) {
    }

    public function execute(string $name, string $email, string $phone): Contact
    {
        $emailVO = new Email($email);
        $phoneVO = new Phone($phone);

        $contact = Contact::create($name, $emailVO, $phoneVO);

        $this->repository->save($contact);

        return $contact;
    }
}
