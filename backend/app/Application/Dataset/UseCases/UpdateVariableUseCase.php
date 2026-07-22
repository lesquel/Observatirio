<?php

declare(strict_types=1);

namespace App\Application\Dataset\UseCases;

use App\Application\Auth\Services\AuthorizationService;
use App\Domain\Departamento\Repositories\DepartamentoRepositoryInterface;
use App\Domain\Dataset\Repositories\DatasetRepositoryInterface;
use App\Domain\Dataset\Repositories\VariableMetadatoRepositoryInterface;
use App\Domain\User\Entities\User as DomainUser;
use App\Models\User;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

class UpdateVariableUseCase
{
    public function __construct(
        private readonly VariableMetadatoRepositoryInterface $variableRepository,
        private readonly DatasetRepositoryInterface $datasetRepository,
        private readonly DepartamentoRepositoryInterface $departamentoRepository,
        private readonly AuthorizationService $authorizationService,
    ) {}

    public function execute(string $variableId, int $userId, array $data): array
    {
        $variable = $this->variableRepository->findById($variableId);

        if (!$variable) {
            throw new HttpException(Response::HTTP_NOT_FOUND, 'Variable no encontrada');
        }

        $dataset = $this->datasetRepository->findById($variable->datasetId);
        
        if (!$dataset) {
            throw new HttpException(Response::HTTP_NOT_FOUND, 'Dataset no encontrado');
        }

        $eloquentUser = User::find($userId);
        if (!$eloquentUser) {
            throw new HttpException(Response::HTTP_UNAUTHORIZED, 'Usuario no encontrado');
        }

        $domainUser = new DomainUser(
            id: $eloquentUser->id,
            name: $eloquentUser->name ?? '',
            email: $eloquentUser->email ?? '',
            rol: $eloquentUser->rol,
        );

        if (!$this->authorizationService->canWriteVariable($domainUser, $dataset->departamentoId)) {
            throw new HttpException(Response::HTTP_FORBIDDEN, 'No tienes permisos para modificar variables');
        }

        // Actualizar variable
        $updatedVariable = $variable->update(
            tipoDato: $data['tipo_dato'] ?? null,
            esVisible: $data['es_visible'] ?? null,
            nombreColumna: $data['nombre_columna'] ?? null,
        );

        $savedVariable = $this->variableRepository->update($updatedVariable);

        return $savedVariable->toArray();
    }
}
