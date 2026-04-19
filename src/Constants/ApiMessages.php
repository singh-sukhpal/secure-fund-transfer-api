<?php

namespace App\Constants;

final class ApiMessages
{
    // Success
    public const ACCOUNT_CREATED = 'Account created!';
    public const TRANSFER_COMPLETED = 'Transfer completed!';

    // Errors / Validation
    public const VALIDATION_FAILED = 'Validation failed!';
    public const ACCOUNT_ALREADY_EXISTS = 'Account already exists!';
    public const INSUFFICIENT_FUNDS = 'Insufficient funds!';
    public const NOT_FOUND = 'Not Found!';
    public const INTERNAL_ERROR = 'Internal Server Error!';
    public const TRANSFER_FAILED = 'Transfer failed!';
    public const TRANSFER_SUCCESS = 'Transfer successful!';
    public const ACCOUNT_NOT_FOUND = 'Account not found!';
    public const DUPLICATE_REFERENCE = 'Duplicate referenceId with different payload!';
    public const SAME_ACCOUNT_TRANSFER = 'Cannot transfer to same account!';
    public const HTTP_ERROR = 'HTTP Error!';
    public const DEADLOCK_RETRY_FAILED= 'Deadlock retry failed!';
    public const TOO_MANY_REQUESTS = 'Too many requests, please try later!';
    public const TRANSFER_PROCESS_QUEUE= 'Transfer queued for processing!';
    public const TRANSACTION_NOT_FOUND = 'Transaction not found!';
    public const TRANSFER_STATUS = 'Fetched Transfer Status Successfully!';
    private function __construct() {}
}