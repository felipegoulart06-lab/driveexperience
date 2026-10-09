<?php

declare(strict_types=1);

namespace App\Domain;

final class DocumentTemplates
{
    public static function all(): array
    {
        return [
            'ficha_reserva' => ['Ficha de reserva', self::ficha()],
            'comprovante_agendamento' => ['Comprovante de agendamento', self::comprovante()],
            'ordem_servico' => ['Ordem de serviço', self::ordem()],
            'recibo_pagamento' => ['Recibo de pagamento', self::recibo()],
            'orcamento' => ['Orçamento', self::orcamento()],
            'termo_responsabilidade' => ['Termo de ciência e responsabilidade', self::termo()],
            'relatorio_financeiro' => ['Relatório financeiro', self::relatorio()],
            'relatorio_reservas' => ['Relatório de reservas', self::relatorio()],
            'relatorio_clientes' => ['Relatório de clientes', self::relatorio()],
            'relatorio_vendas_origem' => ['Relatório de vendas por origem', self::relatorio()],
            'ficha_producao' => ['Ficha de produção', self::producao()],
            'comprovante_entrega' => ['Comprovante de entrega', self::entrega()],
        ];
    }

    private static function ficha(): string
    {
        return <<<'HTML'
<p>Reserva <strong>{{codigo}}</strong> · {{status_operacional}} · {{status_financeiro}}</p>
<table>
<tr><th>Cliente</th><td>{{cliente_nome}}</td><th>Documento</th><td>{{cliente_documento}}</td></tr>
<tr><th>Telefone</th><td>{{cliente_telefone}}</td><th>E-mail</th><td>{{cliente_email}}</td></tr>
<tr><th>Cidade</th><td colspan="3">{{cliente_cidade}}</td></tr>
<tr><th>Origem</th><td>{{origem}}</td><th>Plataforma</th><td>{{plataforma}}</td></tr>
<tr><th>Código externo</th><td>{{codigo_externo}}</td><th>Reserva externa</th><td>{{reserva_externa}}</td></tr>
<tr><th>Veículo</th><td>{{veiculo}}</td><th>Experiência</th><td>{{experiencia}}</td></tr>
<tr><th>Data</th><td>{{data}}</td><th>Horário</th><td>{{horario}}</td></tr>
<tr><th>Duração</th><td>{{duracao}}</td><th>Participantes</th><td>{{participantes}}</td></tr>
<tr><th>Responsável</th><td colspan="3">{{responsavel}}</td></tr>
</table>
<h2>Valores</h2>
<table>
<tr><th>Original</th><td>{{valor_original}}</td><th>Desconto</th><td>{{desconto}}</td></tr>
<tr><th>Taxas</th><td>{{taxas}}</td><th>Valor final</th><td>{{valor_final}}</td></tr>
<tr><th>Recebido</th><td>{{valor_pago}}</td><th>Pendente</th><td>{{saldo}}</td></tr>
</table>
{{pagamentos}}
<p><strong>Observações</strong><br>{{observacoes}}</p>
HTML;
    }

    private static function comprovante(): string
    {
        return <<<'HTML'
<p>Olá, {{cliente_nome}}. Este comprovante confirma o agendamento da sua experiência com a Drive Xperience.</p>
<table>
<tr><th>Reserva</th><td>{{codigo}}</td></tr>
<tr><th>Experiência</th><td>{{experiencia}}</td></tr>
<tr><th>Veículo</th><td>{{veiculo}}</td></tr>
<tr><th>Data</th><td>{{data}}</td></tr>
<tr><th>Horário</th><td>{{horario}}</td></tr>
<tr><th>Participantes</th><td>{{participantes}}</td></tr>
<tr><th>Local de referência</th><td>{{endereco}}</td></tr>
</table>
<p>Chegue com antecedência e leve um documento de identificação. Em caso de imprevisto, fale com a equipe pelo telefone {{telefone_empresa}}.</p>
HTML;
    }

    private static function ordem(): string
    {
        return <<<'HTML'
<p>Documento interno da operação. Reserva {{codigo}} · {{status_operacional}}</p>
<table>
<tr><th>Cliente</th><td>{{cliente_nome}}</td><th>WhatsApp</th><td>{{cliente_telefone}}</td></tr>
<tr><th>Veículo</th><td>{{veiculo}}</td><th>Placa</th><td>{{placa}}</td></tr>
<tr><th>Experiência</th><td>{{experiencia}}</td><th>Participantes</th><td>{{participantes}}</td></tr>
<tr><th>Início</th><td>{{data}} {{horario}}</td><th>Duração</th><td>{{duracao}}</td></tr>
<tr><th>Responsável</th><td colspan="3">{{responsavel}}</td></tr>
</table>
<p><strong>Observações operacionais</strong><br>{{observacoes}}</p>
<p>Confira o veículo, o horário e a identificação do cliente antes de iniciar.</p>
HTML;
    }

    private static function recibo(): string
    {
        return <<<'HTML'
<p>Recibo interno do pagamento lançado no sistema. Este documento não substitui comprovante bancário nem documento fiscal.</p>
<table>
<tr><th>Recibo</th><td>{{documento}}</td><th>Reserva</th><td>{{codigo}}</td></tr>
<tr><th>Cliente</th><td colspan="3">{{cliente_nome}}</td></tr>
<tr><th>Valor deste lançamento</th><td>{{valor_lancamento}}</td><th>Data</th><td>{{data_pagamento}}</td></tr>
<tr><th>Forma</th><td>{{forma_pagamento}}</td><th>Parcelas</th><td>{{parcelas}}</td></tr>
<tr><th>Referência</th><td colspan="3">{{referencia}}</td></tr>
<tr><th>Total da reserva</th><td>{{valor_final}}</td><th>Saldo restante</th><td>{{saldo}}</td></tr>
</table>
HTML;
    }

    private static function orcamento(): string
    {
        return <<<'HTML'
<p>Proposta {{codigo}} · válida até {{validade}} · {{status_orcamento}}</p>
<p>Cliente: <strong>{{cliente_nome}}</strong></p>
{{tabela}}
<p><strong>Total: {{valor_final}}</strong></p>
<p>{{condicoes}}</p>
<p>{{observacoes}}</p>
HTML;
    }

    private static function termo(): string
    {
        return <<<'HTML'
<p><strong>Modelo operacional.</strong> Este texto deve ser validado por um profissional jurídico antes do uso definitivo com clientes. Ele não substitui essa revisão.</p>
<p>Eu, {{cliente_nome}}, documento {{cliente_documento}}, declaro que recebi as orientações da Drive Xperience sobre a experiência {{experiencia}}, marcada para {{data}} às {{horario}}, no veículo {{veiculo}}.</p>
<p>Declaro que informei corretamente meus dados, que seguirei as orientações do instrutor e que a atividade depende de condições operacionais, climáticas e de segurança. A empresa pode remarcar ou interromper a experiência quando a segurança exigir.</p>
<p>{{politica}}</p>
<p>Balneário Camboriú, {{emissao}}.</p>
<table>
<tr><th>Cliente</th><th>Equipe Drive Xperience</th></tr>
<tr><td style="height:70px">Assinatura: __________________________</td><td>Assinatura: __________________________</td></tr>
</table>
HTML;
    }

    private static function relatorio(): string
    {
        return <<<'HTML'
<p>Período: {{periodo}}</p>
<p>{{resumo}}</p>
{{tabela}}
HTML;
    }

    private static function producao(): string
    {
        return <<<'HTML'
<table>
<tr><th>Cliente</th><td>{{cliente_nome}}</td><th>Pacote</th><td>{{pacote}}</td></tr>
<tr><th>Valor</th><td>{{valor_final}}</td><th>Contratação</th><td>{{data}}</td></tr>
<tr><th>Produção</th><td>{{status_producao}}</td><th>Responsável</th><td>{{responsavel}}</td></tr>
<tr><th>Previsão</th><td>{{previsao}}</td><th>Entrega</th><td>{{entrega}}</td></tr>
<tr><th>Reserva</th><td colspan="3">{{codigo}}</td></tr>
</table>
<p>{{observacoes}}</p>
HTML;
    }

    private static function entrega(): string
    {
        return <<<'HTML'
<p>Registro interno de entrega dos arquivos do pacote {{pacote}} ao cliente {{cliente_nome}}.</p>
<table>
<tr><th>Entrega</th><td>{{entrega}}</td></tr>
<tr><th>Previsão</th><td>{{previsao}}</td></tr>
<tr><th>Link privado</th><td>{{link}}</td></tr>
</table>
<p>O link não deve ser publicado. O acesso permanece restrito ao cliente e à equipe.</p>
<p>Assinatura de recebimento: ______________________________</p>
HTML;
    }
}
