<?php

namespace App\Handler\UseCase\Statistics;

use App\Entity\Tenants\Statistics\Statistics;
use App\Interface\Configuration\CustomeEntityManagerInterface;
use App\Interface\UseCase\Security\LogInterface;
use App\Message\Statistics\RecordStatisticsMessage;
use App\Repository\Tenants\Domains\DomainsRepository;
use App\Repository\Users\UsersRepository;
use App\Interface\Service\Statistics\UserAgentParserInterface;
use App\Util\UUIDUtil;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class RecordStatisticsHandler
{
    public function __construct(
        private DomainsRepository             $domainsRepository,
        private UserAgentParserInterface      $uaParser,
        private CustomeEntityManagerInterface $entityManager,
        private LogInterface                  $log,
        private UsersRepository               $usersRepository,
    ) {}

    public function __invoke(RecordStatisticsMessage $message): void
    {
        try {
            $domain = $this->domainsRepository->find(
                UUIDUtil::convertIdToSearch($message->domainId)
            );

            if ($domain === null) {
                return;
            }

            $uaData = $this->uaParser->parse($message->userAgent ?? '');

            if ($uaData->device === 'bot') {
                return;
            }

            $user = $message->userId !== null
                ? $this->usersRepository->find(UUIDUtil::convertIdToSearch($message->userId))
                : null;

            $stat = (new Statistics())->add(
                domain:          $domain,
                ip:              $message->ip,
                device:          $uaData->device,
                country:         $message->country,
                region:          $message->region,
                city:            $message->city,
                browser:         $uaData->browser,
                operativeSystem: $uaData->os,
                lang:            $message->lang,
                page:            $message->page,
                referrer:        $message->referrer,
                referrerDomain:  $this->extractReferrerDomain($message->referrer),
                utmSource:       $message->utmSource,
                utmMedium:       $message->utmMedium,
                utmCampaign:     $message->utmCampaign,
                sessionId:       $message->sessionId,
                countryIsoCode:  $message->countryIsoCode,
                user:            $user,
            );

            $this->entityManager->add($stat, true);

        } catch (\Throwable $th) {
            $this->log->handler($th);
        }
    }

    private function extractReferrerDomain(?string $referrer): ?string
    {
        if (empty($referrer)) {
            return null;
        }
        return parse_url($referrer, PHP_URL_HOST) ?: null;
    }
}
