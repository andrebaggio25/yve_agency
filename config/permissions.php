<?php

/**
 * Mapa canônico de permissões do sistema.
 * Usado pelo seeder para popular a tabela `permissions`.
 *
 * Formato: 'module.action' => 'Descrição legível'
 */
return [
    // Dashboard
    'dashboard.view'              => 'Ver dashboard',

    // Clientes
    'clients.view'                => 'Ver clientes (próprios)',
    'clients.view_all'            => 'Ver todos os clientes',
    'clients.create'              => 'Criar cliente',
    'clients.edit'                => 'Editar cliente',
    'clients.delete'              => 'Excluir cliente',

    // Contatos
    'contacts.view'               => 'Ver contatos',
    'contacts.create'             => 'Criar contato',
    'contacts.edit'               => 'Editar contato',
    'contacts.delete'             => 'Excluir contato',

    // Conteúdo / Planificação
    'content.view'                => 'Ver planificações',
    'content.create'              => 'Criar planificação',
    'content.edit'                => 'Editar planificação',
    'content.delete'              => 'Excluir planificação',
    'content.send_to_approval'    => 'Enviar para aprovação',
    'content.approve'             => 'Aprovar conteúdo',

    // Aprovações (perfil cliente)
    'approvals.view'              => 'Ver aprovações',
    'approvals.comment'           => 'Comentar aprovação',
    'approvals.approve'           => 'Aprovar',
    'approvals.reject'            => 'Reprovar',

    // Assets / Drive
    'drive_assets.view'           => 'Ver arquivos do Drive',

    // Métricas
    'organic_metrics.view'        => 'Ver métricas orgânicas',
    'ads_metrics.view'            => 'Ver métricas de anúncios',
    'ads_actions.view'            => 'Ver ações em campanha',
    'ads_actions.request'         => 'Solicitar ação em campanha',
    'ads_actions.approve'         => 'Aprovar ação em campanha',
    'ads_actions.execute'         => 'Executar ação em campanha',

    // IA
    'ai_insights.view'            => 'Ver insights de IA',
    'ai.generate_report'          => 'Gerar relatório com IA',
    'ai.generate_content'         => 'Gerar conteúdo com IA',
    'ai.recommend_ads_action'     => 'IA recomendar ação em campanha',
    'ai.approve_ads_action'       => 'Aprovar ação recomendada pela IA',
    'ai.execute_ads_action'       => 'Executar ação aprovada pela IA',
    'ai.send_client_message'      => 'IA enviar mensagem ao cliente',

    // Automações
    'automations.view'            => 'Ver automações',
    'automations.create'          => 'Criar automação',
    'automations.edit'            => 'Editar automação',
    'automations.delete'          => 'Excluir automação',
    'automations.execute'         => 'Executar automação',

    // WhatsApp
    'whatsapp.view'               => 'Ver mensagens WhatsApp',
    'whatsapp.send'               => 'Enviar WhatsApp',
    'whatsapp.manage'             => 'Gerenciar instâncias WhatsApp',

    // E-mail
    'email.view'                  => 'Ver e-mails',
    'email.send'                  => 'Enviar e-mail',
    'email.manage'                => 'Gerenciar templates de e-mail',

    // Tarefas
    'tasks.view'                  => 'Ver tarefas',
    'tasks.create'                => 'Criar tarefa',
    'tasks.edit'                  => 'Editar tarefa',
    'tasks.delete'                => 'Excluir tarefa',

    // Contratos
    'contracts.view'              => 'Ver contratos',
    'contracts.create'            => 'Criar contrato',
    'contracts.edit'              => 'Editar contrato',
    'contracts.delete'            => 'Excluir contrato',
    'contracts.send'              => 'Enviar contrato',

    // Faturas
    'invoices.view'               => 'Ver faturas',
    'invoices.create'             => 'Criar fatura',
    'invoices.edit'               => 'Editar fatura',
    'invoices.delete'             => 'Excluir fatura',
    'invoices.send'               => 'Enviar fatura',

    // Pagamentos
    'payments.view'               => 'Ver pagamentos',
    'payments.create'             => 'Registrar pagamento',
    'payments.edit'               => 'Editar pagamento',
    'payments.delete'             => 'Excluir pagamento',

    // Relatórios financeiros
    'financial_reports.view'      => 'Ver relatórios financeiros',
    'financial_reports.export'    => 'Exportar relatórios financeiros',

    // Usuários
    'users.view'                  => 'Ver usuários',
    'users.create'                => 'Criar usuário',
    'users.edit'                  => 'Editar usuário',
    'users.delete'                => 'Excluir usuário',

    // Roles
    'roles.view'                  => 'Ver perfis de acesso',
    'roles.create'                => 'Criar perfil',
    'roles.edit'                  => 'Editar perfil',
    'roles.delete'                => 'Excluir perfil',

    // Configurações
    'settings.view'               => 'Ver configurações',
    'settings.edit'               => 'Editar configurações',

    // Logs
    'logs.view'                   => 'Ver logs de atividade',

    // Criativos
    'creative_library.view'       => 'Ver biblioteca de criativos',
    'brand_guidelines.view'       => 'Ver brand guidelines',
    'brand_guidelines.edit'       => 'Editar brand guidelines',

    // Configurações
    'settings.manage'             => 'Gerenciar configurações da agência',

    // Portal do cliente
    'portal.view'                 => 'Ver portal do cliente',
    'portal.manage'               => 'Gerenciar portal (token, ativar/desativar)',
];
