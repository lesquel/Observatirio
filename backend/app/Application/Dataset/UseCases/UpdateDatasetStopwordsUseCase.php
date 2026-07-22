<?php

declare(strict_types=1);

namespace App\Application\Dataset\UseCases;

use App\Application\Auth\Services\AuthorizationService;
use App\Domain\Departamento\Repositories\DepartamentoRepositoryInterface;
use App\Domain\Dataset\Repositories\DatasetRepositoryInterface;
use App\Domain\User\Entities\User as DomainUser;
use App\Models\User;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class UpdateDatasetStopwordsUseCase
{
    public function __construct(
        private readonly DatasetRepositoryInterface $datasetRepository,
        private readonly DepartamentoRepositoryInterface $departamentoRepository,
        private readonly AuthorizationService $authorizationService,
    ) {}

    public function execute(string $datasetId, int $userId, array $stopwords): array
    {
        $dataset = $this->datasetRepository->findById($datasetId);

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

        if (!$this->authorizationService->canWriteDataset($domainUser, $dataset->departamentoId)) {
            throw new HttpException(Response::HTTP_FORBIDDEN, 'No tienes permisos para modificar este dataset');
        }

        // Clean and validate stopwords
        $cleanStopwords = collect($stopwords)
            ->map(fn($word) => mb_strtolower(trim($word)))
            ->filter(fn($word) => mb_strlen($word) >= 2)
            ->unique()
            ->values()
            ->toArray();

        // Get current opciones and merge
        $currentOpciones = DB::table('datasets')
            ->where('id', $datasetId)
            ->value('opciones');

        $opciones = $currentOpciones ? json_decode($currentOpciones, true) : [];
        $opciones['custom_stopwords'] = $cleanStopwords;

        DB::table('datasets')
            ->where('id', $datasetId)
            ->update(['opciones' => json_encode($opciones)]);

        return [
            'stopwords' => $cleanStopwords,
            'count' => count($cleanStopwords),
        ];
    }
}
