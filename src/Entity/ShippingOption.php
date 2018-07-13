<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

/**
 * @ORM\Entity
 */
class ShippingOption
{
    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=32)
     */
    private $username;

    /**
     * @ORM\Column(type="string")
     */
    private $shippingOption;

    /**
     * Price in fiat
     *
     * @ORM\Column(type="decimal", precision=8, scale=7)
     */
    private $price;

    /**
     * AUD
     * CAD
     * CHF
     * EUR
     * GBP
     * RUB
     * USD
     *
     * @ORM\Column(type="string")
     */
    private $fiat;

    public function getId()
    {
        return $this->id;
    }

    public function setId($id)
    {
        $this->id = $id;
    }

    public function getUsername()
    {
        return $this->username;
    }

    public function setUsername($username)
    {
        $this->username = $username;
    }

    public function getShippingOption()
    {
        return $this->shippingOption;
    }

    public function setShippingOption($shippingOption)
    {
        $this->shippingOption = $shippingOption;
    }

    public function getPrice()
    {
        return $this->price;
    }

    public function setPrice($price)
    {
        $this->price = $price;
    }

    public function getFiat()
    {
        return $this->fiat;
    }

    public function setFiat($fiat)
    {
        $this->fiat = $fiat;
    }
}
