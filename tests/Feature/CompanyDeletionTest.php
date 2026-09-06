<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Car;
use App\Models\CarDocument;
use App\Models\CarService;
use App\Models\Company;
use App\Models\CompanySubscriptionPaidMonth;
use App\Models\CompanySubscriptionPayment;
use App\Models\DekurzAiFeedback;
use App\Models\Document;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\PatientPoint;
use App\Models\ReportMonth;
use App\Models\Role;
use App\Models\ScanSession;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CompanyDeletionTest extends TestCase
{
    use RefreshDatabase;

    private function ensureManagerRole(): void
    {
        if (!Role::where('position', 'manager')->exists()) {
            Role::create(['position' => 'manager', 'scope' => 'company']);
        }
    }

    /**
     * Builds one of everything associated with a Company, so the deletion cascade can be
     * verified against a realistic, fully-populated tree rather than a bare Company row.
     */
    private function seedFullCompany(): array
    {
        $this->ensureManagerRole();

        $company = Company::factory()->create([
            'name' => 'Zmazať s.r.o.',
            'subscription_status' => 'trial',
            'subscription_ends_at' => now()->addDays(5),
        ]);
        $manager = User::factory()->create([
            'company_id' => $company->id,
            'role_id' => Role::where('position', 'manager')->value('id'),
        ]);
        $manager->createToken('test-token');

        $branch = Branch::factory()->create(['company_id' => $company->id]);
        $patient = Patient::factory()->create(['branch_id' => $branch->id]);

        $document = Document::create([
            'patient_id' => $patient->id,
            'user_id' => $manager->id,
            'type' => 'nalez',
            'mime_type' => 'application/pdf',
            'name' => 'doc.pdf',
            'path' => 'documents/doc.pdf',
        ]);

        $invoice = Invoice::create([
            'user_id' => $manager->id,
            'name' => 'invoice.pdf',
            'path' => 'invoices/invoice.pdf',
            'period' => '2026-01',
        ]);

        $patientPoint = PatientPoint::create(['patient_id' => $patient->id, 'branch_id' => $branch->id]);
        $visit = Visit::create(['patient_id' => $patient->id, 'branch_id' => $branch->id, 'user_id' => $manager->id]);
        $scanSession = ScanSession::create([
            'patient_id' => $patient->id,
            'branch_id' => $branch->id,
            'user_id' => $manager->id,
            'session_token' => 'tok-123',
            'status' => 'pending',
            'expires_at' => now()->addHour(),
        ]);
        $feedback = DekurzAiFeedback::create([
            'document_id' => $document->id,
            'patient_id' => $patient->id,
            'user_id' => $manager->id,
            'branch_id' => $branch->id,
            'source' => 'test',
            'suggested_sections' => [],
            'final_sections' => [],
        ]);
        $reportMonth = ReportMonth::create([
            'month' => 1,
            'year' => 2026,
            'branch_id' => $branch->id,
            'user_id' => $manager->id,
        ]);

        $car = Car::factory()->create(['company_id' => $company->id]);
        $carDocument = CarDocument::create(['car_id' => $car->id, 'mime_type' => 'application/pdf', 'path' => 'cars/doc.pdf']);
        $carService = CarService::create(['car_id' => $car->id, 'name' => 'Oil change', 'date' => now(), 'interval_days' => 180]);

        DB::table('procedure_company_prices')->insert([
            'procedure_id' => \App\Models\Procedure::factory()->create()->id,
            'insurance_company_id' => \App\Models\InsuranceCompany::factory()->create()->id,
            'company_id' => $company->id,
            'price' => 10,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $payment = CompanySubscriptionPayment::create([
            'company_id' => $company->id,
            'amount' => 19,
            'received_at' => now(),
        ]);
        $paidMonth = CompanySubscriptionPaidMonth::create([
            'company_id' => $company->id,
            'year' => 2026,
            'month' => 1,
        ]);

        return compact(
            'company', 'manager', 'branch', 'patient', 'document', 'invoice',
            'patientPoint', 'visit', 'scanSession', 'feedback', 'reportMonth',
            'car', 'carDocument', 'carService', 'payment', 'paidMonth'
        );
    }

    public function test_deleting_a_company_soft_deletes_core_records_and_hard_deletes_operational_data(): void
    {
        $seed = $this->seedFullCompany();
        $company = $seed['company'];
        $manager = $seed['manager'];

        $resp = $this->actingAs($manager)->deleteJson('/api/v1/my-company', [
            'confirm_name' => 'Zmazať s.r.o.',
        ]);

        $resp->assertStatus(200);

        // Soft-deleted (recoverable) - excluded from default queries, still present withTrashed().
        $this->assertNull(Company::find($company->id));
        $this->assertNotNull(Company::withTrashed()->find($company->id));

        $this->assertNull(User::find($manager->id));
        $this->assertNotNull(User::withTrashed()->find($manager->id));

        $this->assertNull(Patient::find($seed['patient']->id));
        $this->assertNotNull(Patient::withTrashed()->find($seed['patient']->id));

        $this->assertNull(Document::find($seed['document']->id));
        $this->assertNotNull(Document::withTrashed()->find($seed['document']->id));

        $this->assertNull(Invoice::find($seed['invoice']->id));
        $this->assertNotNull(Invoice::withTrashed()->find($seed['invoice']->id));

        // Hard-deleted (no independent recovery value once the Company is gone).
        $this->assertNull(Branch::find($seed['branch']->id));
        $this->assertNull(Car::find($seed['car']->id));
        $this->assertNull(CarDocument::find($seed['carDocument']->id));
        $this->assertNull(CarService::find($seed['carService']->id));
        $this->assertNull(PatientPoint::find($seed['patientPoint']->id));
        $this->assertNull(Visit::find($seed['visit']->id));
        $this->assertNull(ScanSession::find($seed['scanSession']->id));
        $this->assertNull(DekurzAiFeedback::find($seed['feedback']->id));
        $this->assertNull(ReportMonth::find($seed['reportMonth']->id));
        $this->assertNull(CompanySubscriptionPayment::find($seed['payment']->id));
        $this->assertNull(CompanySubscriptionPaidMonth::find($seed['paidMonth']->id));
        $this->assertEquals(0, DB::table('procedure_company_prices')->where('company_id', $company->id)->count());

        // Every API token for every affected User is gone - the user is logged out.
        $this->assertEquals(0, DB::table('personal_access_tokens')
            ->where('tokenable_type', User::class)
            ->where('tokenable_id', $manager->id)
            ->count());
    }

    public function test_deletion_requires_the_company_name_to_be_typed_exactly(): void
    {
        $this->ensureManagerRole();
        $company = Company::factory()->create([
            'name' => 'Presný Názov s.r.o.',
            'subscription_status' => 'trial',
            'subscription_ends_at' => now()->addDays(5),
        ]);
        $manager = User::factory()->create([
            'company_id' => $company->id,
            'role_id' => Role::where('position', 'manager')->value('id'),
        ]);

        $resp = $this->actingAs($manager)->deleteJson('/api/v1/my-company', [
            'confirm_name' => 'Nesprávny názov',
        ]);

        $resp->assertStatus(422);
        $this->assertNotNull(Company::find($company->id));
    }

    public function test_deletion_requires_confirm_name_field(): void
    {
        $this->ensureManagerRole();
        $company = Company::factory()->create([
            'subscription_status' => 'trial',
            'subscription_ends_at' => now()->addDays(5),
        ]);
        $manager = User::factory()->create([
            'company_id' => $company->id,
            'role_id' => Role::where('position', 'manager')->value('id'),
        ]);

        $this->actingAs($manager)->deleteJson('/api/v1/my-company', [])
            ->assertStatus(422);
    }

    public function test_a_deleted_users_token_can_no_longer_authenticate(): void
    {
        $seed = $this->seedFullCompany();
        $manager = $seed['manager'];
        $token = $manager->createToken('logout-check')->plainTextToken;

        // Deliberately not using actingAs() here - it binds the user to the guard directly
        // and would keep "authenticating" every later request regardless of the token's
        // real state, masking exactly the behavior this test needs to prove.
        $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson('/api/v1/my-company', ['confirm_name' => 'Zmazať s.r.o.'])
            ->assertStatus(200);

        // The sanctum guard caches the resolved user for the lifetime of this test's shared
        // app container - force it to re-resolve from the (now-deleted) token on the next call.
        $this->app['auth']->forgetGuards();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/my-company')
            ->assertStatus(401);
    }
}
