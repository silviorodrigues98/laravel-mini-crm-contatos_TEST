<?php

namespace App\Infrastructure\Repositories;

use App\Infrastructure\Models\Contact as ContactModel;
use App\Infrastructure\Persistence\Mappers\ContactMapper;
use Domain\Entities\Contact;
use Domain\Repositories\ContactRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class EloquentContactRepository implements ContactRepositoryInterface
{
    public function __construct(
        private readonly ContactModel $model,
        private readonly ContactMapper $mapper,
    ) {
    }

    public function save(Contact $contact): void
    {
        $eloquent = $this->mapper->toEloquent($contact);
        $eloquent->save();

        if ($contact->id() === null) {
            $ref = new \ReflectionProperty($contact, 'id');
            $ref->setAccessible(true);
            $ref->setValue($contact, $eloquent->id);
        }
    }

    public function findById(int $id): ?Contact
    {
        $model = $this->model->newQuery()->find($id);

        if ($model === null) {
            return null;
        }

        return $this->mapper->toDomain($model);
    }

    public function findAll(int $perPage = 15, int $page = 1): LengthAwarePaginator
    {
        $paginator = $this->model->newQuery()->paginate(perPage: $perPage, page: $page);

        $paginator->getCollection()->transform(fn (ContactModel $model) => $this->mapper->toDomain($model));

        return $paginator;
    }

    public function delete(int $id): void
    {
        $this->model->newQuery()->findOrFail($id)->delete();
    }
}
