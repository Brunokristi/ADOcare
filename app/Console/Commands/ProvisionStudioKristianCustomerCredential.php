<?php

namespace App\Console\Commands;

use App\Exceptions\StudioKristianBillingException;
use App\Models\Company;
use App\Services\StudioKristianBillingService;
use Illuminate\Console\Command;

/**
 * Manual/ops tool to attach a StudioKristian Customer Credential to a Company
 * before the full onboarding flow provisions it automatically.
 */
class ProvisionStudioKristianCustomerCredential extends Command
{
    protected $signature = 'billing:provision-customer {company_id : ID of the Company to provision} {--name= : Optional label for the credential in StudioKristian}';

    protected $description = 'Provision a StudioKristian Billing Customer Credential for a Company and store it locally';

    public function handle(StudioKristianBillingService $billing): int
    {
        $company = Company::find((int) $this->argument('company_id'));

        if (!$company) {
            $this->error('Company not found.');
            return self::FAILURE;
        }

        if ($company->hasBillingCustomerToken()) {
            $this->warn("Company #{$company->id} already has a StudioKristian customer credential.");
            return self::SUCCESS;
        }

        try {
            $token = $billing->provisionCustomerCredential($company, $this->option('name'));
        } catch (StudioKristianBillingException $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        $company->update(['studiokristian_customer_token' => $token]);

        $this->info("StudioKristian customer credential provisioned for Company #{$company->id}.");

        return self::SUCCESS;
    }
}

