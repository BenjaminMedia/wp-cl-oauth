<?php

namespace Bonnier\WP\OAuth\Providers;

use League\OAuth2\Client\Provider\ResourceOwnerInterface;
use League\OAuth2\Client\Tool\ArrayAccessorTrait;

class CommonLoginResourceOwner implements ResourceOwnerInterface
{
    use ArrayAccessorTrait;

    protected $response;

    public function __construct(array $response = [])
    {
        $this->response = $response;
    }

    /**
     * Returns the identifier of the authorized resource owner.
     *
     * @return mixed
     */
    public function getId()
    {
        return $this->getValueByKey($this->response, 'id');
    }

    public function getEmail()
    {
        return $this->getValueByKey($this->response, 'email');
    }

    public function getFirstName()
    {
        return $this->getValueByKey($this->response, 'first_name');
    }

    public function getLastName()
    {
        return $this->getValueByKey($this->response, 'last_name');
    }

    public function getSubscriptionNumber()
    {
        return $this->getValueByKey($this->response, 'subscription_number');
    }

    public function getCreatedAt()
    {
        return $this->getValueByKey($this->response, 'created_at');
    }

    public function getRoles()
    {
        return $this->getValueByKey($this->response, 'roles');
    }

    /**
     * Return all of the owner details available as an array.
     *
     * @return array
     */
    public function toArray()
    {
        return $this->response;
    }
}
