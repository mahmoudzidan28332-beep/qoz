<?php
declare(strict_types=1);

final class JobsService
{
    public const WHITELISTED_COLUMNS = [
        'entity_id', 'slug', 'job_type', 'employment_type',
        'application_form_type', 'external_application_url', 'experience_level',
        'category', 'department', 'positions_available',
        'salary_min', 'salary_max', 'salary_currency', 'salary_period', 'salary_negotiable',
        'country_id', 'city_id', 'work_location', 'is_remote',
        'status', 'application_deadline', 'start_date',
        'views_count', 'applications_count', 'is_featured', 'is_urgent',
        'created_by', 'published_at', 'closed_at'
    ];

    public function __construct(PdoJobsRepository $repo, $validator = null)
    {
        $this->repo = $repo;
        $this->validator = $validator;
    }

    public function list(
        ?int $tenantId,
        ?int $limit,
        ?int $offset,
        array $filters = [],
        string $orderBy = 'id',
        string $orderDir = 'DESC',
        string $lang = 'ar'
    ): array {
        return $this->repo->all($tenantId, $limit, $offset, $filters, $orderBy, $orderDir, $lang);
    }

    public function count(?int $tenantId, array $filters = [], string $lang = 'ar'): int
    {
        return $this->repo->count($tenantId, $filters, $lang);
    }

    /**
     * Get single job by ID
     */
    public function get(int $id, string $lang = 'ar'): ?array
    {
        return $this->repo->find($id, $lang);
    }

    /**
     * Get single job by slug
     */
    public function getBySlug(string $slug, string $lang = 'ar'): ?array
    {
        return $this->repo->findBySlug($slug, $lang);
    }

    /**
     * Get all translations for a job
     */
    public function getTranslations(int $jobId): array
    {
        return $this->repo->getTranslations($jobId);
    }

    public function create(array $data): int
    {
        // 🔒 SECURITY: Mass Assignment Protection - Define WHITELIST
        $whitelisted = array_intersect_key($data, array_flip(self::WHITELISTED_COLUMNS));

        // Validate
        if ($this->validator) {
            $this->validator->validate($whitelisted, false);
        }

        $jobId = $this->repo->save($whitelisted);

        // Save translation if provided
        if ($jobId && !empty($data['job_title'])) {
            $lang = $data['language_code'] ?? 'ar';
            $this->saveTranslation($jobId, $lang, $data);
        }

        return $jobId;
    }

    public function update(array $data): int
    {
        if (empty($data['id'])) {
            throw new InvalidArgumentException('ID is required for update');
        }

        // 🔒 SECURITY: Mass Assignment Protection - Define WHITELIST
        $whitelisted = array_intersect_key($data, array_flip(self::WHITELISTED_COLUMNS));

        // Validate
        if ($this->validator) {
            $this->validator->validate($whitelisted, true);
        }

        $jobId = $this->repo->save($whitelisted);

        // Update translation if provided
        if (!empty($data['job_title'])) {
            $lang = $data['language_code'] ?? 'ar';
            $this->saveTranslation($jobId, $lang, $data);
        }

        return $jobId;
    }

    /**
     * Save or update translation
     */
    public function saveTranslation(int $jobId, string $languageCode, array $data): bool
    {
        // Validate translation data
        if ($this->validator) {
            $translationData = array_merge($data, ['language_code' => $languageCode]);
            $this->validator->validateTranslation($translationData);
        }

        return $this->repo->saveTranslation($jobId, $languageCode, $data);
    }

    /**
     * Delete translation
     */
    public function deleteTranslation(int $jobId, string $languageCode): bool
    {
        return $this->repo->deleteTranslation($jobId, $languageCode);
    }

    /**
     * Delete job
     */
    public function delete(int $id): bool
    {
        return $this->repo->delete($id);
    }

    /**
     * Increment view count
     */
    public function incrementViews(int $id): bool
    {
        return $this->repo->incrementViews($id);
    }

    /**
     * Increment applications count
     */
    public function incrementApplications(int $id): bool
    {
        return $this->repo->incrementApplications($id);
    }

    /**
     * Update job status
     */
    public function updateStatus(int $id, string $status): bool
    {
        return $this->repo->updateStatus($id, $status);
    }

    /**
     * Publish job
     */
    public function publish(int $id): bool
    {
        return $this->updateStatus($id, 'published');
    }

    /**
     * Close job
     */
    public function close(int $id): bool
    {
        return $this->updateStatus($id, 'closed');
    }

    /**
     * Mark job as filled
     */
    public function markAsFilled(int $id): bool
    {
        return $this->updateStatus($id, 'filled');
    }

    /**
     * Cancel job
     */
    public function cancel(int $id): bool
    {
        return $this->updateStatus($id, 'cancelled');
    }

    public function getFeatured(?int $tenantId, int $limit = 10, string $lang = 'ar'): array
    {
        return $this->list(
            $tenantId,
            $limit,
            0,
            ['is_featured' => 1, 'status' => 'published'],
            'created_at',
            'DESC',
            $lang
        );
    }

    public function getUrgent(?int $tenantId, int $limit = 10, string $lang = 'ar'): array
    {
        return $this->list(
            $tenantId,
            $limit,
            0,
            ['is_urgent' => 1, 'status' => 'published'],
            'created_at',
            'DESC',
            $lang
        );
    }

    public function getRemote(?int $tenantId, int $limit = 10, string $lang = 'ar'): array
    {
        return $this->list(
            $tenantId,
            $limit,
            0,
            ['is_remote' => 1, 'status' => 'published'],
            'created_at',
            'DESC',
            $lang
        );
    }

    public function search(
        ?int $tenantId,
        string $keyword,
        ?int $limit = null,
        ?int $offset = null,
        string $lang = 'ar'
    ): array {
        return $this->list(
            $tenantId,
            $limit,
            $offset,
            ['search' => $keyword, 'status' => 'published'],
            'created_at',
            'DESC',
            $lang
        );
    }
}
