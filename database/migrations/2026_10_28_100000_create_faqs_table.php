<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The public website's frequently asked questions, edited by staff under Contents → FAQs. It starts
 * with questions whose answers are already stated elsewhere on the site.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('faqs', function (Blueprint $table) {
            $table->id();
            $table->string('question');
            $table->text('answer');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_published')->default(true);
            $table->timestamps();
        });

        $now = now();
        DB::table('faqs')->insert(collect([
            ['Where is your office?', 'Unit 1 & 2 Astrofield Bldg, Mitchell Avenue, Balibago, Angeles City, Pampanga. You can find us on Google Maps from the Contact section of this website.'],
            ['What are your office hours?', 'Monday to Friday, 8:30 AM to 5:30 PM, and Saturday, 8:30 AM to 3:30 PM. Visa Assistance on Saturdays is by appointment.'],
            ['How can I pay?', 'You can pay in cash at our office, by GCash, by bank transfer, or by credit or debit card. Ask our staff for the account details, and you will get an official receipt for every payment.'],
            ['Can I book a tour package online?', 'Yes. Open the package, fill in the booking form with your travel date and number of travellers, and send it. You get a booking reference straight away, and our team contacts you to confirm the details and payment.'],
            ['Do you help with visas and immigration?', 'Yes. We handle visa assistance, Bureau of Immigration services such as tourist visa extensions, passporting, and the SRRV retiree visa. See the Immigration Pricing page for the BI fees.'],
        ])->map(fn (array $faq, int $i) => [
            'question' => $faq[0], 'answer' => $faq[1], 'sort_order' => $i + 1, 'is_published' => true,
            'created_at' => $now, 'updated_at' => $now,
        ])->all());
    }

    public function down(): void
    {
        Schema::dropIfExists('faqs');
    }
};
