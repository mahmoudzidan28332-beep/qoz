<?php
declare(strict_types=1);

namespace App\Models\Jobs\Validators;

use InvalidArgumentException;

final class JobApplicationsValidator
{
    private const STATUSES = [
        'submitted', 'under_review', 'shortlisted', 'interview_scheduled',
        'interviewed', 'offered', 'accepted', 'rejected', 'withdrawn'
    ];

    public function validate(array $data, bool $isUpdate = false): void
    {
        if (!$isUpdate) {
            $required = ['job_id', 'user_id', 'full_name', 'email', 'phone', 'cv_file_url'];
            foreach ($required as $field) {
                if (!isset($data[$field]) || $data[$field] === '') { throw new InvalidArgumentException("Field '{$field}' is required."); }
            }
        }

        if (isset($data['job_id']) && (!is_numeric($data['job_id']) || $data['job_id'] <= 0)) { throw new InvalidArgumentException("job_id must be a positive integer."); }
        if (isset($data['user_id']) && (!is_numeric($data['user_id']) || $data['user_id'] <= 0)) { throw new InvalidArgumentException("user_id must be a positive integer."); }

        $this->validateTextFields($data);
        $this->validateNumericFields($data);
        $this->validateUrlFields($data);

        if (isset($data['status']) && !in_array($data['status'], self::STATUSES, true)) { throw new InvalidArgumentException("Invalid status. Allowed: " . implode(', ', self::STATUSES)); }
        if (isset($data['rating']) && $data['rating'] !== null && (!is_numeric($data['rating']) || $data['rating'] < 1 || $data['rating'] > 5)) { throw new InvalidArgumentException("rating must be between 1 and 5."); }
        if (isset($data['ip_address']) && $data['ip_address'] !== null && strlen($data['ip_address']) > 45) { throw new InvalidArgumentException("ip_address must be at most 45 characters."); }
    }

    private function validateTextFields(array $data): void
    {
        if (isset($data['full_name'])) {
            if (strlen($data['full_name']) > 255) { throw new InvalidArgumentException("full_name must be at most 255 characters."); }
            if (trim($data['full_name']) === '') { throw new InvalidArgumentException("full_name cannot be empty."); }
        }
        if (isset($data['email'])) {
            if (strlen($data['email']) > 191) { throw new InvalidArgumentException("email must be at most 191 characters."); }
            if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) { throw new InvalidArgumentException("Invalid email format."); }
        }
        if (isset($data['phone'])) {
            if (strlen($data['phone']) > 45) { throw new InvalidArgumentException("phone must be at most 45 characters."); }
            if (trim($data['phone']) === '') { throw new InvalidArgumentException("phone cannot be empty."); }
        }
        if (isset($data['current_position']) && $data['current_position'] !== null && strlen($data['current_position']) > 255) { throw new InvalidArgumentException("current_position must be at most 255 characters."); }
        if (isset($data['current_company']) && $data['current_company'] !== null && strlen($data['current_company']) > 255) { throw new InvalidArgumentException("current_company must be at most 255 characters."); }
        if (isset($data['currency_code']) && $data['currency_code'] !== null && strlen($data['currency_code']) > 8) { throw new InvalidArgumentException("currency_code must be at most 8 characters."); }
    }

    private function validateNumericFields(array $data): void
    {
        if (isset($data['years_of_experience']) && $data['years_of_experience'] !== null && (!is_numeric($data['years_of_experience']) || $data['years_of_experience'] < 0)) { throw new InvalidArgumentException("years_of_experience must be a non-negative integer."); }
        if (isset($data['expected_salary']) && $data['expected_salary'] !== null && (!is_numeric($data['expected_salary']) || $data['expected_salary'] < 0)) { throw new InvalidArgumentException("expected_salary must be a non-negative number."); }
        if (isset($data['notice_period']) && $data['notice_period'] !== null && (!is_numeric($data['notice_period']) || $data['notice_period'] < 0)) { throw new InvalidArgumentException("notice_period must be a non-negative integer (in days)."); }
    }

    private function validateUrlFields(array $data): void
    {
        if (isset($data['cv_file_url'])) {
            if (strlen($data['cv_file_url']) > 500) { throw new InvalidArgumentException("cv_file_url must be at most 500 characters."); }
            if (trim($data['cv_file_url']) === '') { throw new InvalidArgumentException("cv_file_url cannot be empty."); }
        }
        if (isset($data['portfolio_url']) && $data['portfolio_url'] !== null && $data['portfolio_url'] !== '') {
            if (strlen($data['portfolio_url']) > 500) { throw new InvalidArgumentException("portfolio_url must be at most 500 characters."); }
            if (!filter_var($data['portfolio_url'], FILTER_VALIDATE_URL)) { throw new InvalidArgumentException("portfolio_url must be a valid URL."); }
        }
        if (isset($data['linkedin_url']) && $data['linkedin_url'] !== null && $data['linkedin_url'] !== '') {
            if (strlen($data['linkedin_url']) > 500) { throw new InvalidArgumentException("linkedin_url must be at most 500 characters."); }
            if (!filter_var($data['linkedin_url'], FILTER_VALIDATE_URL)) { throw new InvalidArgumentException("linkedin_url must be a valid URL."); }
        }
    }

    /**
     * التحقق من صحة تحديث الحالة
     */
    public function validateStatusUpdate(string $status): void
    {
        if (!in_array($status, self::STATUSES, true)) {
            throw new InvalidArgumentException("Invalid status. Allowed: " . implode(', ', self::STATUSES));
        }
    }

    /**
     * التحقق من صحة التقييم
     */
    public function validateRating(int $rating): void
    {
        if ($rating < 1 || $rating > 5) {
            throw new InvalidArgumentException("rating must be between 1 and 5.");
        }
    }
}
