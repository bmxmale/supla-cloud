<?php
namespace SuplaBundle\Command\User;

use Assert\Assertion;
use Doctrine\ORM\EntityManagerInterface;
use SuplaBundle\Entity\EntityUtils;
use SuplaBundle\EventListener\ApiRateLimit\ApiRateLimitRule;
use SuplaBundle\EventListener\ApiRateLimit\ApiRateLimitStorage;
use SuplaBundle\EventListener\ApiRateLimit\DefaultUserApiRateLimit;
use SuplaBundle\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

class ChangeUserLimitsCommand extends ContainerAwareCommand {
    /** @var UserRepository */
    private $userRepository;
    /** @var EntityManagerInterface */
    private $entityManager;
    /** @var DefaultUserApiRateLimit */
    private $defaultUserApiRateLimit;
    /** @var ApiRateLimitStorage */
    private $apiRateLimitStorage;

    public function __construct(
        UserRepository $userRepository,
        EntityManagerInterface $entityManager,
        ApiRateLimitStorage $apiRateLimitStorage,
        DefaultUserApiRateLimit $defaultUserApiRateLimit
    ) {
        parent::__construct();
        $this->userRepository = $userRepository;
        $this->entityManager = $entityManager;
        $this->apiRateLimitStorage = $apiRateLimitStorage;
        $this->defaultUserApiRateLimit = $defaultUserApiRateLimit;
    }

    protected function configure() {
        $this
            ->setName('supla:user:change-limits')
            ->setAliases(['supla:change-user-limits'])
            ->setDescription('Allows to change user limits.')
            ->addArgument('username', InputArgument::OPTIONAL, 'Username to update.')
            ->addArgument('limitForAll', InputArgument::OPTIONAL, 'Limit value to set for all limits.');
    }

    protected function execute(InputInterface $input, OutputInterface $output) {
        /** @var QuestionHelper $helper */
        $helper = $this->getHelper('question');
        $email = $input->getArgument('username')
            ?: $helper->ask($input, $output, new Question('Whose limits do you want to change? (email address): '));
        $user = $this->userRepository->findOneByEmail($email);
        Assertion::notNull($user, 'Such user does not exist.');
        $limitForAll = $input->getArgument('limitForAll');
        foreach ([
                     'limitAid' => 'Access Identifiers',
                     'limitChannelGroup' => 'Channel Groups',
                     'limitChannelPerGroup' => 'Channels per Channel Group',
                     'limitDirectLink' => 'Direct Links',
                     'limitLoc' => 'Locations',
                     'limitOAuthClient' => 'OAuth Clients',
                     'limitSchedule' => 'Schedules',
                 ] as $field => $label) {
            $currentLimit = EntityUtils::getField($user, $field);
            $newLimit = $limitForAll ?: $helper->ask($input, $output, new Question("Limit of $label [$currentLimit]: ", $currentLimit));
            EntityUtils::setField($user, $field, $newLimit);
        }
        if (!$limitForAll) {
            $currentRule = $user->getApiRateLimit() ?: $this->defaultUserApiRateLimit;
            $newRule = $helper->ask($input, $output, $this->apiRateLimitQuestion($currentRule));
            if ($newRule != $currentRule) {
                $user->setApiRateLimit($newRule == $this->defaultUserApiRateLimit ? null : $newRule);
                $this->apiRateLimitStorage->clearUserLimit($user);
            }
        }
        $this->entityManager->persist($user);
        $this->entityManager->flush();
        $output->writeln('<info>User limits have been updated.</info>');
    }

    private function apiRateLimitQuestion(ApiRateLimitRule $currentLimit): Question {
        $q = new Question("API Rate limit (req/sec or default) [$currentLimit]: ", $currentLimit);
        $q->setValidator(function ($v) {
            if ($v === 'default') {
                return $this->defaultUserApiRateLimit;
            } else {
                $rule = new ApiRateLimitRule($v);
                Assertion::true($rule->isValid(), 'Invalid API rate limit rule. Format: limit/seconds');
                return $rule;
            }
        });
        return $q;
    }
}
