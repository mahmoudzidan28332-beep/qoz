<?php
declare(strict_types=1);

class ApplicationException extends AppException
{
    protected int $statusCode = 400;

    /**
     * @param string         $message
     * @param int|array      $statusCodeOrContext  HTTP status code (int) or context data (array).
     *                                             Int form is the original calling convention;
     *                                             array form is the current convention.
     * @param \Throwable|null $previous
     */
    public function __construct(
        string $message = 'Application error',
        int|array $statusCodeOrContext = [],
        ?\Throwable $previous = null
    ) {
        if (is_int($statusCodeOrContext)) {
            if ($statusCodeOrContext > 0) {
                $this->statusCode = $statusCodeOrContext;
            }
            $this->context = [];
        } else {
            $this->context = $statusCodeOrContext;
        }
        parent::__construct($message, 0, $previous);
    }
}
