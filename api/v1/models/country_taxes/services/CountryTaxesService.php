<?php
declare(strict_types=1);

// api/v1/models/country_taxes/services/CountryTaxesService.php

/*
|--------------------------------------------------------------------------
| Required dependencies (NO autoload, NO namespace)
|--------------------------------------------------------------------------
*/
require_once __DIR__ . '/../repositories/PdoCountryTaxesRepository.php';
require_once __DIR__ . '/../validators/CountryTaxesValidator.php';

final class CountryTaxesService
{
    private PdoCountryTaxesRepository $repo;
    private CountryTaxesValidator $validator;

    private const WHITELISTED_COLUMNS = [
        'id', 'country_id', 'tax_class_id', 'tax_name', 'tax_name_ar',
        'tax_type', 'tax_rate', 'is_inclusive', 'is_active', 'effective_date'
    ];

    public function __construct(
        PdoCountryTaxesRepository $repo,
        CountryTaxesValidator $validator
    ) {
        $this->repo      = $repo;
        $this->validator = $validator;
    }

    public function list(?int $countryId = null, ?int $taxClassId = null): array
    {
        return $this->repo->all($countryId, $taxClassId);
    }

    public function get(int $id): array
    {
        $row = $this->repo->find($id);
        if (!$row) {
            throw new ApplicationException('Country tax not found');
        }

        return $row;
    }

    public function save(array $data, ?int $userId = null): array
    {
        $data = array_intersect_key($data, array_flip(self::WHITELISTED_COLUMNS));
        $errors = $this->validator->validate($data);
        if (!empty($errors)) {
            throw new InvalidArgumentException(
                json_encode($errors, JSON_UNESCAPED_UNICODE)
            );
        }

        $id = $this->repo->save($data, $userId);

        $row = $this->repo->find($id);
        if (!$row) {
            throw new ApplicationException('Failed to load saved country tax');
        }

        return $row;
    }

    public function delete(int $id, ?int $userId = null): void
    {
        if (!$this->repo->delete($id, $userId)) {
            throw new ApplicationException('Failed to delete country tax');
        }
    }

    public function getByCountry(int $countryId): array
    {
        return $this->repo->all($countryId);
    }

    public function getByTaxClass(int $taxClassId): array
    {
        return $this->repo->all(null, $taxClassId);
    }
}