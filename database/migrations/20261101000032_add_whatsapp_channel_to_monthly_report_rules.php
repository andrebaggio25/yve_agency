<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * BUG-07 — o motor passou a respeitar `automation_rules.channels`, e o
 * relatório mensal ficaria sem canal nenhum.
 *
 * O catálogo dizia `channels: ['email']` para `report.client_monthly`, mas o
 * handler sempre enviou por **WhatsApp** (e-mail só chega no WPP-01). Enquanto
 * ninguém lia a coluna isso era só a tela mentindo sobre o canal; agora o canal
 * desmarcado impede a entrega — e toda agência que abriu /automations já tem a
 * linha gravada com `["email"]` pelo `ensureRulesForAgency`. Sem este ajuste, o
 * relatório que hoje sai pararia de sair calado (apareceria como "Não enviado"
 * na timeline de entregas).
 *
 * Só toca linha que tem **exatamente** o padrão antigo — escolha explícita do
 * tenant (por exemplo, nenhum canal) não é alterada. Sem tabela, coluna ou FK
 * nova: se o deploy acontecer antes de a migration rodar pelo painel
 * `/admin/migrations`, nada quebra — o relatório mensal fica em "Não enviado"
 * até o ajuste (ou até a agência marcar WhatsApp na tela). Reversível: `down()`
 * devolve exatamente o valor anterior.
 */
final class AddWhatsappChannelToMonthlyReportRules extends AbstractMigration
{
    private const KEY = 'report.client_monthly';

    public function up(): void
    {
        $this->getAdapter()->getConnection()->prepare(
            "UPDATE automation_rules
                SET channels = '[\"whatsapp\", \"email\"]'::jsonb, updated_at = NOW()
              WHERE automation_key = :k
                AND channels = '[\"email\"]'::jsonb"
        )->execute([':k' => self::KEY]);
    }

    public function down(): void
    {
        $this->getAdapter()->getConnection()->prepare(
            "UPDATE automation_rules
                SET channels = '[\"email\"]'::jsonb, updated_at = NOW()
              WHERE automation_key = :k
                AND channels = '[\"whatsapp\", \"email\"]'::jsonb"
        )->execute([':k' => self::KEY]);
    }
}
