<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Create the companies table
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('computer_card')->nullable();
            $table->string('branch_number')->nullable();
            $table->string('contact_person')->nullable();
            $table->string('phone_number')->nullable();
            $table->string('alternative_phone_number')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        // 2. Add company_id to staff
        Schema::table('staff', function (Blueprint $table) {
            $table->unsignedBigInteger('company_id')->nullable()->after('id');
            $table->foreign('company_id')->references('id')->on('companies')->nullOnDelete();
        });

        // 3. Migrate data from staff to companies
        $staffCompanies = DB::table('staff')
            ->whereNotNull('company_name')
            ->select('company_name', 'company_contact_person', 'company_phone')
            ->distinct()
            ->get();

        foreach ($staffCompanies as $sc) {
            $companyId = DB::table('companies')->insertGetId([
                'name' => $sc->company_name,
                'contact_person' => $sc->company_contact_person,
                'phone_number' => $sc->company_phone,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('staff')
                ->where('company_name', $sc->company_name)
                ->update(['company_id' => $companyId]);
        }

        // 4. Drop the denormalized columns from staff
        Schema::table('staff', function (Blueprint $table) {
            $table->dropColumn(['company_name', 'company_contact_person', 'company_phone']);
        });
    }

    public function down(): void
    {
        Schema::table('staff', function (Blueprint $table) {
            $table->string('company_name')->nullable()->after('status');
            $table->string('company_contact_person')->nullable()->after('company_name');
            $table->string('company_phone')->nullable()->after('company_contact_person');
        });

        $staffWithCompany = DB::table('staff')
            ->join('companies', 'staff.company_id', '=', 'companies.id')
            ->select('staff.id', 'companies.name', 'companies.contact_person', 'companies.phone_number')
            ->get();

        foreach ($staffWithCompany as $item) {
            DB::table('staff')->where('id', $item->id)->update([
                'company_name' => $item->name,
                'company_contact_person' => $item->contact_person,
                'company_phone' => $item->phone_number,
            ]);
        }

        Schema::table('staff', function (Blueprint $table) {
            $table->dropForeign(['company_id']);
            $table->dropColumn('company_id');
        });

        Schema::dropIfExists('companies');
    }
};
