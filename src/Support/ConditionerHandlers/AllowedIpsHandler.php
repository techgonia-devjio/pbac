<?php

namespace Pbac\Support\ConditionerHandlers;

use Illuminate\Database\Eloquent\Model;
use Pbac\Contracts\ConditionHandlerInterface;
use Pbac\Models\PBACAccessControl;
use Pbac\Support\PbacLogger;

class AllowedIpsHandler implements ConditionHandlerInterface
{
    public function __construct(private PbacLogger $logger)
    {
    }

    public function handle(Model $user, string $action, ?string $resourceTypeString, ?int $resourceId, array $context, mixed $conditionValue, PBACAccessControl $rule): bool
    {
        if (!isset($context['ip']) && !isset($context['ip_address'])) {
            $this->logger->debug("Rule ID {$rule->id} requires an IP, but none was provided in the context.");
            return false; // Deny if IP is required but not provided
        }

        $allowedIps = (array) $conditionValue;
        $userIp = $context['ip_address'] ?? $context['ip'];
        $ipMatched = false;

        foreach ($allowedIps as $allowedIp) {
            if (str_contains($allowedIp, '/')) { // It's a CIDR
                if ($this->isIpInCidr($userIp, $allowedIp)) {
                    $ipMatched = true;
                    break;
                }
            } elseif ($userIp === $allowedIp) { // Direct IP match
                $ipMatched = true;
                break;
            }
        }

        if (!$ipMatched) {
            $this->logger->debug("Rule ID {$rule->id} failed 'allowed_ips' condition: IP {$userIp} not in allowed list.");
            return false;
        }

        return true;
    }

    private function isIpInCidr(string $ip, string $cidr): bool
    {
        // TODO(in-future): For a robust solution, we should consider using a dedicated IP library like 'php-ip/php-ip'
        // This is a basic implementation suitable for common IPv4 CIDR checks.
        if (!str_contains($cidr, '/')) {
            return $ip === $cidr; // Not a CIDR, just a single IP comparison
        }
        [$subnet, $mask] = explode('/', $cidr);
        $subnetLong = ip2long($subnet);
        $ipLong = ip2long($ip);
        $maskLong = ~((1 << (32 - $mask)) - 1); // Calculate the bitmask
        return ($ipLong & $maskLong) === ($subnetLong & $maskLong);
    }
}
