<?php
declare(strict_types=1);

final class JobApplicationsService
{
    public const WHITELISTED_COLUMNS = [
        'job_id', 'user_id', 'full_name', 'email', 'phone',
        'current_position', 'current_company', 'years_of_experience',
        'expected_salary', 'currency_code', 'notice_period',
        'cv_file_url', 'cover_letter', 'portfolio_url', 'linkedin_url',
        'status', 'rating', 'notes', 'reviewed_by', 'reviewed_at', 'ip_address'
    ];

    public function __construct(PdoJobApplicationsRepository $repo, $validator = null)
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
     * Get single application by ID
     */
    public function get(int $id, string $lang = 'ar'): ?array
    {
        return $this->repo->find($id, $lang);
    }

    /**
     * Get applications by job ID
     */
    public function getByJob(int $jobId, string $lang = 'ar'): array
    {
        return $this->repo->getByJob($jobId, $lang);
    }

    /**
     * Get applications by user ID
     */
    public function getByUser(int $userId, string $lang = 'ar'): array
    {
        return $this->repo->getByUser($userId, $lang);
    }

    /**
     * Check if user already applied
     */
    public function hasApplied(int $jobId, int $userId): bool
    {
        return $this->repo->hasApplied($jobId, $userId);
    }

    /**
     * Get statistics for a job
     */
    public function getStatistics(int $jobId): array
    {
        return $this->repo->getStatistics($jobId);
    }

    public function create(array $data): int
    {
        // 🔒 SECURITY: Mass Assignment Protection - Define WHITELIST
        $whitelisted = array_intersect_key($data, array_flip(self::WHITELISTED_COLUMNS));

        // Validate
        if ($this->validator) {
            $this->validator->validate($whitelisted, false);
        }

        // Check if user already applied
        if (isset($whitelisted['job_id']) && isset($whitelisted['user_id'])) {
            if ($this->hasApplied((int)$whitelisted['job_id'], (int)$whitelisted['user_id'])) {
                throw new ApplicationException('User has already applied for this job.');
            }
        }

        return $this->repo->save($whitelisted);
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

        return $this->repo->save($whitelisted);
    }

    /**
     * Delete application
     */
    public function delete(int $id): bool
    {
        return $this->repo->delete($id);
    }

    /**
     * Update application status
     */
    public function updateStatus(int $id, string $status): bool
    {
        // Validate status
        if ($this->validator) {
            $this->validator->validateStatusUpdate($status);
        }

        return $this->repo->updateStatus($id, $status);
    }

    /**
     * Update rating
     */
    public function updateRating(int $id, int $rating, ?int $reviewedBy = null): bool
    {
        // Validate rating
        if ($this->validator) {
            $this->validator->validateRating($rating);
        }

        return $this->repo->updateRating($id, $rating, $reviewedBy);
    }

    /**
     * Add review/notes
     */
    public function addReview(int $id, string $notes, int $reviewedBy): bool
    {
        if (trim($notes) === '') {
            throw new InvalidArgumentException('Review notes cannot be empty.');
        }

        return $this->repo->addReview($id, $notes, $reviewedBy);
    }

    /**
     * Shortlist application
     */
    public function shortlist(int $id): bool
    {
        return $this->updateStatus($id, 'shortlisted');
    }

    /**
     * Reject application
     */
    public function reject(int $id): bool
    {
        return $this->updateStatus($id, 'rejected');
    }

    /**
     * Schedule interview
     */
    public function scheduleInterview(int $id): bool
    {
        return $this->updateStatus($id, 'interview_scheduled');
    }

    /**
     * Mark as interviewed
     */
    public function markInterviewed(int $id): bool
    {
        return $this->updateStatus($id, 'interviewed');
    }

    /**
     * Make offer
     */
    public function makeOffer(int $id): bool
    {
        return $this->updateStatus($id, 'offered');
    }

    /**
     * Accept offer
     */
    public function acceptOffer(int $id): bool
    {
        return $this->updateStatus($id, 'accepted');
    }

    /**
     * Withdraw application
     */
    public function withdraw(int $id): bool
    {
        return $this->updateStatus($id, 'withdrawn');
    }

    /**
     * Move to under review
     */
    public function moveToUnderReview(int $id): bool
    {
        return $this->updateStatus($id, 'under_review');
    }
}
