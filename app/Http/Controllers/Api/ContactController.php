<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreContactRequest;
use App\Http\Requests\UpdateContactRequest;
use App\Http\Resources\ContactCollection;
use App\Http\Resources\ContactResource;
use App\Infrastructure\Models\Contact as ContactModel;
use Application\UseCases\CreateContactUseCase;
use Application\UseCases\DeleteContactUseCase;
use Application\UseCases\GetContactUseCase;
use Application\UseCases\ListContactsUseCase;
use Application\UseCases\UpdateContactUseCase;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ContactController extends Controller
{
    public function __construct(
        private readonly CreateContactUseCase $createContact,
        private readonly ListContactsUseCase $listContacts,
        private readonly GetContactUseCase $getContact,
        private readonly UpdateContactUseCase $updateContact,
        private readonly DeleteContactUseCase $deleteContact,
    ) {
    }

    public function store(StoreContactRequest $request): JsonResponse
    {
        $data = $request->validated();

        $contact = $this->createContact->execute(
            $data['name'],
            $data['email'],
            $data['phone'],
        );

        return ContactResource::make($contact)
            ->response()
            ->setStatusCode(201);
    }

    public function index(Request $request): ContactCollection
    {
        $perPage = $request->integer('per_page', 15);
        $page = $request->integer('page', 1);

        $contacts = $this->listContacts->execute(
            perPage: $perPage,
            page: $page,
        );

        $total = ContactModel::count();

        $paginator = new LengthAwarePaginator(
            $contacts,
            $total,
            $perPage,
            $page,
            ['path' => LengthAwarePaginator::resolveCurrentPath()]
        );

        return new ContactCollection($paginator);
    }

    public function show(int $id): JsonResponse
    {
        $contact = $this->getContact->execute($id);

        if ($contact === null) {
            return response()->json(['message' => 'Not Found'], 404);
        }

        return ContactResource::make($contact)->response();
    }

    public function update(UpdateContactRequest $request, int $id): JsonResponse
    {
        $data = $request->validated();

        $contact = $this->updateContact->execute(
            $id,
            $data['name'],
            $data['email'],
            $data['phone'],
        );

        if ($contact === null) {
            return response()->json(['message' => 'Not Found'], 404);
        }

        return ContactResource::make($contact)->response();
    }

    public function destroy(int $id): Response
    {
        $this->deleteContact->execute($id);

        return response()->noContent();
    }
}
