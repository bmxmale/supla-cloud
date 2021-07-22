<?php
/*
 Copyright (C) AC SOFTWARE SP. Z O.O.

 This program is free software; you can redistribute it and/or
 modify it under the terms of the GNU General Public License
 as published by the Free Software Foundation; either version 2
 of the License, or (at your option) any later version.
 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.
 You should have received a copy of the GNU General Public License
 along with this program; if not, write to the Free Software
 Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
 */

namespace SuplaBundle\Tests\Integration\Model\ChannelParamsTranslator;

use SuplaBundle\Entity\IODevice;
use SuplaBundle\Entity\User;
use SuplaBundle\Enums\ChannelFunction;
use SuplaBundle\Enums\ChannelType;
use SuplaBundle\Model\ChannelParamsTranslator\ChannelParamConfigTranslator;
use SuplaBundle\Tests\Integration\IntegrationTestCase;
use SuplaBundle\Tests\Integration\Traits\SuplaApiHelper;

/** @small */
class ControllingRolerShutterTimerIntegrationTest extends IntegrationTestCase {
    use SuplaApiHelper;

    /** @var IODevice */
    private $device;
    /** @var ChannelParamConfigTranslator */
    private $paramsTranslator;
    /** @var User */
    private $user;

    public function initializeDatabaseForTests() {
        $this->user = $this->createConfirmedUser();
        $location = $this->createLocation($this->user);
        $this->device = $this->createDevice($location, [
            [ChannelType::RELAY, ChannelFunction::CONTROLLINGTHEROLLERSHUTTER],
            [ChannelType::SENSORNO, ChannelFunction::OPENINGSENSOR_ROLLERSHUTTER],
        ]);
    }

    /** @before */
    public function init() {
        $this->paramsTranslator = self::$container->get(ChannelParamConfigTranslator::class);
        $this->simulateAuthentication($this->user);
    }

    public function testUpdatingControllingTheRollerShutterTime() {
        $channel = $this->device->getChannels()[0];
        $this->assertEquals(0, $channel->getParam1());
        $this->assertEquals(0, $channel->getParam3());
        $this->paramsTranslator->setParamsFromConfig($channel, []);
        $this->assertEquals(0, $channel->getParam1());
        $this->assertEquals(0, $channel->getParam3());
        $this->paramsTranslator->setParamsFromConfig($channel, ['openingTimeS' => 100, 'closingTimeS' => 0]);
        $this->assertEquals(1000, $channel->getParam1());
        $this->assertEquals(0, $channel->getParam3());
        $this->paramsTranslator->setParamsFromConfig($channel, ['openingTimeS' => 100, 'closingTimeS' => 300]);
        $this->assertEquals(1000, $channel->getParam1());
        $this->assertEquals(3000, $channel->getParam3());
        $this->paramsTranslator->setParamsFromConfig($channel, ['openingTimeS' => 1000, 'closingTimeS' => 3000]);
        $this->assertEquals(3000, $channel->getParam1());
        $this->assertEquals(3000, $channel->getParam3());
    }

    public function testSettingOpeningSensorForRollerShutter() {
        $channel = $this->device->getChannels()[0];
        $this->paramsTranslator->setParamsFromConfig($channel, ['openingSensorChannelId' => $this->device->getChannels()[1]->getId()]);
        $this->device = $this->getEntityManager()->find(IODevice::class, $this->device->getId());
        $this->assertEquals($channel->getId(), $this->device->getChannels()[1]->getParam1());
        $this->assertEquals($this->device->getChannels()[1]->getId(), $this->device->getChannels()[0]->getParam2());
    }

    public function testSettingRollerShutterForOpeningSensor() {
        $sensor = $this->device->getChannels()[1];
        $this->paramsTranslator->setParamsFromConfig($sensor, ['openingSensorChannelId' => $this->device->getChannels()[0]->getId()]);
        $this->device = $this->getEntityManager()->find(IODevice::class, $this->device->getId());
        $this->assertEquals($sensor->getId(), $this->device->getChannels()[0]->getParam2());
        $this->assertEquals($this->device->getChannels()[0]->getId(), $this->device->getChannels()[1]->getParam1());
    }
}