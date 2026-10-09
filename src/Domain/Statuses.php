<?php

declare(strict_types=1);

namespace App\Domain;

final class Statuses
{
    public const OPERATIONAL = [
        'novo' => 'Novo atendimento',
        'aguardando_dados' => 'Aguardando dados',
        'aguardando_confirmacao' => 'Aguardando confirmação',
        'confirmado' => 'Confirmado',
        'em_andamento' => 'Em andamento',
        'concluido' => 'Concluído',
        'cancelado' => 'Cancelado',
        'reagendamento_solicitado' => 'Reagendamento solicitado',
    ];

    public const FINANCIAL = [
        'sem_cobranca' => 'Sem cobrança',
        'aguardando_pagamento' => 'Aguardando pagamento',
        'parcial' => 'Parcial',
        'pago' => 'Pago',
    ];

    public const PRODUCTION = [
        'nao_iniciado' => 'Não iniciado',
        'agendado' => 'Agendado',
        'em_producao' => 'Em produção',
        'em_edicao' => 'Em edição',
        'pronto' => 'Pronto para entrega',
        'entregue' => 'Entregue',
    ];

    public const VEHICLE = [
        'disponivel' => 'Disponível',
        'reservado' => 'Reservado',
        'em_utilizacao' => 'Em utilização',
        'em_manutencao' => 'Em manutenção',
        'indisponivel' => 'Indisponível',
    ];

    public const TRANSITIONS = [
        'novo' => ['aguardando_dados', 'aguardando_confirmacao', 'confirmado', 'cancelado', 'reagendamento_solicitado'],
        'aguardando_dados' => ['aguardando_confirmacao', 'confirmado', 'cancelado', 'reagendamento_solicitado'],
        'aguardando_confirmacao' => ['confirmado', 'aguardando_dados', 'cancelado', 'reagendamento_solicitado'],
        'confirmado' => ['em_andamento', 'cancelado', 'reagendamento_solicitado', 'aguardando_confirmacao'],
        'em_andamento' => ['concluido', 'cancelado'],
        'reagendamento_solicitado' => ['aguardando_confirmacao', 'confirmado', 'cancelado'],
        'concluido' => [],
        'cancelado' => [],
    ];

    public static function label(string $group, ?string $code): string
    {
        $map = match ($group) {
            'financial' => self::FINANCIAL,
            'production' => self::PRODUCTION,
            'vehicle' => self::VEHICLE,
            default => self::OPERATIONAL,
        };
        return $map[$code ?? ''] ?? ($code ?: '—');
    }
}
