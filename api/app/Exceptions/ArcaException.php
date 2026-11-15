<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Wraps an ARCA / AFIP error with a clear retryable flag.
 *
 * Retryable  = true  → transient error (network timeout, SOAP fault, lock busy).
 *                       The caller MUST use the same submission_key on retry.
 *
 * Retryable  = false → permanent error (ARCA rejection, bad data, cert invalid).
 *                       Retrying with the same data will never succeed.
 */
class ArcaException extends RuntimeException
{
    private bool $retryable;
    private ?string $submissionKey;
    private bool $definitivelyRejected = false;

    public function isDefinitivelyRejected(): bool { return $this->definitivelyRejected; }

    public static function rejected(string $message, ?\Throwable $previous = null): self
    {
        $error = new self($message, false, null, $previous);
        $error->definitivelyRejected = true;
        return $error;
    }

    public function __construct(string $message, bool $retryable, ?string $submissionKey = null, ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
        $this->retryable     = $retryable;
        $this->submissionKey = $submissionKey;
    }

    public function isRetryable(): bool
    {
        return $this->retryable;
    }

    public function getSubmissionKey(): ?string
    {
        return $this->submissionKey;
    }

    /** Wraps any generic exception as a retryable ARCA error. */
    public static function transient(string $message, ?\Throwable $previous = null, ?string $submissionKey = null): self
    {
        return new self($message, true, $submissionKey, $previous);
    }

    /** Wraps a permanent ARCA rejection that cannot be retried. */
    public static function permanent(string $message, ?\Throwable $previous = null): self
    {
        return new self($message, false, null, $previous);
    }
}
