<?php

namespace App\Service;

use App\Entity\ActivityLog;
use App\Entity\Adminuser;
use App\Entity\Staff;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\User\UserInterface;

class ActivityLogger
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly Security $security,
    ) {
    }

    /**
     * @param array<string, mixed> $changes
     */
    public function log(
        string $action,
        string $entityType,
        ?string $entityId,
        string $description,
        ?string $targetData = null,
        array $changes = [],
        bool $flush = true
    ): ActivityLog
    {
        $log = new ActivityLog();
        $log->setAction($action);
        $log->setEntityType($entityType);
        $log->setEntityId($entityId);
        $log->setDescription($description);
        $log->setTargetData($targetData);
        $log->setChanges(!empty($changes) ? $changes : null);
        $log->setCreatedAt(new \DateTimeImmutable());

        $user = $this->security->getUser();
        $this->hydrateActor($log, $user);

        // Ensure we never leave actor fields blank in the log
        if (!$log->getActorName()) {
            $log->setActorName('Unknown');
        }
        if (!$log->getActorRole()) {
            $log->setActorRole($user ? 'ROLE_USER' : 'ANONYMOUS');
        }

        $this->entityManager->persist($log);

        if ($flush) {
            $this->entityManager->flush();
        }

        return $log;
    }

    private function hydrateActor(ActivityLog $log, ?UserInterface $user): void
    {
        if ($user instanceof Adminuser) {
            $log->setActor($user);
            $log->setActorName($user->getFullname());
            $log->setActorEmail($user->getEmail());
            $log->setActorId((string) $user->getId());
            $log->setActorRole('ROLE_ADMIN');
            return;
        }

        if ($user instanceof Staff) {
            $log->setActorName($user->getFullName());
            $log->setActorEmail($user->getEmail());
            $log->setActorId((string) $user->getId());
            $log->setActorRole('ROLE_STAFF');
            return;
        }

        if ($user instanceof UserInterface) {
            $identifier = method_exists($user, 'getUserIdentifier') ? $user->getUserIdentifier() : null;
            $username = method_exists($user, 'getUsername') ? $user->getUsername() : null;
            $label = $username ?: $identifier;
            if ($label) {
                $log->setActorName($label);
                $log->setActorEmail($identifier);
            }
            $roles = method_exists($user, 'getRoles') ? $user->getRoles() : [];
            $log->setActorRole(!empty($roles) ? implode(',', $roles) : 'ROLE_USER');
            return;
        }

        if (is_string($user) && $user !== '') {
            $log->setActorName($user);
            $log->setActorEmail($user);
            $log->setActorRole('ROLE_USER');
            return;
        }

        $log->setActorRole('ANONYMOUS');
    }
}


