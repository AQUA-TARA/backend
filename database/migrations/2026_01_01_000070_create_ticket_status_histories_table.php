<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Physical Design: ticket_status_histories table = Ticket Status History entity.
     * Menyimpan jejak audit setiap perubahan status Ticket.
     *
     * old_status NULLABLE: status pertama saat Ticket dibuat tidak memiliki old_status.
     * new_status NOT NULL: setiap record pasti memiliki status tujuan.
     * actor_id NULLABLE: perubahan bisa dilakukan sistem (tanpa actor admin tertentu).
     *
     * CHECK constraints pada old_status dan new_status memastikan konsistensi
     * dengan canonical lifecycle (DEC-002).
     *
     * Source: PHYSICAL_DATABASE_DESIGN.md §5 ticket_status_history
     * Depends on: tickets, users
     */
    public function up(): void
    {
        Schema::create('ticket_status_histories', function (Blueprint $table) {
            $table->id();                                     // BIGINT UNSIGNED AUTO_INCREMENT PK

            // FK ke tickets.
            // cascadeOnDelete: jika ticket dihapus, histori juga dihapus.
            // Histori tidak bermakna tanpa ticket induknya.
            $table->foreignId('ticket_id')
                  ->constrained('tickets')
                  ->cascadeOnDelete();                        // NOT NULL FK CASCADE

            // Status sebelum perubahan.
            // NULLABLE: saat Ticket pertama kali dibuat (SUBMITTED), tidak ada status sebelumnya.
            $table->string('old_status')->nullable();         // NULLABLE

            // Status setelah perubahan — wajib ada.
            $table->string('new_status');                     // NOT NULL

            // Admin yang melakukan perubahan status.
            // NULLABLE: perubahan dapat dilakukan oleh sistem (misal: auto-trigger).
            // nullOnDelete: jika akun admin dihapus, histori tetap ada (actor → NULL).
            $table->foreignId('actor_id')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();                           // NULLABLE FK

            // Waktu perubahan status terjadi.
            $table->timestamp('created_at')->useCurrent();   // NOT NULL DEFAULT CURRENT_TIMESTAMP
            // Tidak ada updated_at — histori bersifat immutable audit log.
        });

        // CHECK constraint untuk old_status (nullable diizinkan).
        DB::statement("
            ALTER TABLE ticket_status_histories
            ADD CONSTRAINT chk_tsh_old_status
            CHECK (old_status IS NULL OR old_status IN ('SUBMITTED', 'IN_REVIEW', 'CONFIRMED', 'IN_PROGRESS', 'CLOSED'))
        ");

        // CHECK constraint untuk new_status.
        DB::statement("
            ALTER TABLE ticket_status_histories
            ADD CONSTRAINT chk_tsh_new_status
            CHECK (new_status IN ('SUBMITTED', 'IN_REVIEW', 'CONFIRMED', 'IN_PROGRESS', 'CLOSED'))
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ticket_status_histories');
    }
};
