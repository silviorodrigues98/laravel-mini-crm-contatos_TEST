<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreContactRequest;
use App\Http\Resources\ContactCollection;
use App\Http\Resources\ContactResource;
use Application\UseCases\CreateContactUseCase;
use Application\UseCases\ListContactsUseCase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContactController extends Controller
{
    public function __construct(
        private readonly CreateContactUseCase $createContact,
        private readonly ListContactsUseCase $listContacts,
    ) {
    }

    public function store(StoreContactRequest $request): JsonResponse
    {
        $contact = $this->createContact->execute(
            $request->validated()['name'],
            $request->validated()['email'],
            $request->validated()['phone'],
        );

        return ContactResource::make($contact)
            ->response()
            ->setStatusCode(201);
    }

    public function index(Request $request): ContactCollection
    {
        $contacts = $this->listContacts->execute(
            perPage: $request->integer('per_page', 15),
            page: $request->integer('page', 1),
        );

        return new ContactCollection($contacts);
    }
}
