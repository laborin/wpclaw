<?php

declare(strict_types=1);

namespace WPClaw\Agent;

/**
 * Stop reasons placeholder enum for agent loop lifecycle.
 */
enum StopReason: string
{
    case EndTurn = 'end_turn';
    case Cancelled = 'cancelled';
    case MaxIterations = 'max_iterations';
    case Error = 'error';
}
