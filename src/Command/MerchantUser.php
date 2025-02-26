<?php

namespace App\Command;

use Symfony\Component\Security\Core\User\UserInterface;

class MerchantUser implements UserInterface
{
    private string $merchantId;

    public function __construct(string $merchantId)
    {
        $this->merchantId = $merchantId;
    }

    // Implement the required methods from the UserInterface
    public function getRoles(): array
    {
        // Return an array of roles for the merchant
        return ['ROLE_MERCHANT'];
    }

    public function getUserIdentifier(): string
    {
        // Return the username for the merchant (not used in this example)
        return $this->merchantId;
    }

    public function eraseCredentials(): void
    {
        // This method is not used in this example
    }
}
