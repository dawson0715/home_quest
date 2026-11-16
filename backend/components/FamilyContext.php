<?php

declare(strict_types=1);

namespace app\components;

use app\models\ChildProfile;
use app\models\ChildSession;
use app\models\Device;
use app\models\Family;
use app\models\FamilyMember;
use app\models\User;
use Yii;
use yii\base\Component;
use yii\web\IdentityInterface;

/**
 * Who is calling and for which family (application component `familyContext`).
 *
 * Actors: a parent User (role admin|coparent from family_member), a paired Device
 * (role `device`: can only list profiles / start a child session) or a ChildSession
 * (role `child`). Every domain query is scoped with getId() — see FamilyScopedQuery.
 */
class FamilyContext extends Component
{
    public const ROLE_CHILD = 'child';
    public const ROLE_DEVICE = 'device';
    public const PARENT_ROLES = [FamilyMember::ROLE_ADMIN, FamilyMember::ROLE_COPARENT];

    private ?FamilyMember $member = null;
    /** Identity the cached member belongs to: the cache never outlives an identity switch. */
    private ?IdentityInterface $memberFor = null;

    public function getActor(): IdentityInterface
    {
        $identity = Yii::$app->has('user') ? Yii::$app->user->identity : null;
        if ($identity === null) {
            throw new ApiException(ErrorCode::UNAUTHORIZED, 'Authentication required.', 401);
        }
        return $identity;
    }

    /**
     * Family membership of the parent user.
     *
     * @throws ApiException 403 no_family (user without family) / forbidden (not a parent)
     */
    public function getMember(): FamilyMember
    {
        $actor = $this->getActor();
        if ($this->member !== null && $this->memberFor === $actor) {
            return $this->member;
        }
        if (!$actor instanceof User) {
            throw new ApiException(ErrorCode::FORBIDDEN, 'Only parents can do this.', 403);
        }
        $member = FamilyMember::find()->with('family')->where(['user_id' => $actor->id])->one();
        if (!$member instanceof FamilyMember) {
            throw new ApiException(ErrorCode::NO_FAMILY, 'The user does not belong to any family.', 403);
        }
        $this->memberFor = $actor;
        return $this->member = $member;
    }

    /** True when the caller is authenticated and bound to a family. */
    public function isAvailable(): bool
    {
        try {
            $this->getId();
            return true;
        } catch (ApiException) {
            return false;
        }
    }

    public function getId(): int
    {
        $actor = $this->getActor();
        if ($actor instanceof Device || $actor instanceof ChildSession) {
            return (int) $actor->family_id;
        }
        return $this->getMember()->family_id;
    }

    public function getFamily(): Family
    {
        $actor = $this->getActor();
        if ($actor instanceof User) {
            return $this->getMember()->family;
        }
        /** @var Family $family */
        $family = Family::findOne($this->getId());
        return $family;
    }

    public function getRole(): string
    {
        $actor = $this->getActor();
        return match (true) {
            $actor instanceof ChildSession => self::ROLE_CHILD,
            $actor instanceof Device => self::ROLE_DEVICE,
            default => $this->getMember()->role,
        };
    }

    public function isParent(): bool
    {
        return $this->getActor() instanceof User && $this->hasRole(...self::PARENT_ROLES);
    }

    /** The acting child (child sessions only). */
    public function getChild(): ?ChildProfile
    {
        $actor = $this->getActor();
        return $actor instanceof ChildSession ? $actor->child : null;
    }

    /**
     * The game profile of the caller: the child of a child session, or the
     * parent's own profile. 403 for devices.
     */
    public function getPlayer(): ChildProfile
    {
        $actor = $this->getActor();
        $player = match (true) {
            $actor instanceof ChildSession => $actor->child,
            $actor instanceof User => ChildProfile::find()->forFamily($this->getId())
                ->andWhere(['user_id' => $actor->id, 'archived_at' => null])->one(),
            default => null,
        };
        if (!$player instanceof ChildProfile) {
            throw new ApiException(ErrorCode::FORBIDDEN, 'Only players can do this.', 403);
        }
        return $player;
    }

    public function getDevice(): ?Device
    {
        $actor = $this->getActor();
        return match (true) {
            $actor instanceof Device => $actor,
            $actor instanceof ChildSession => $actor->device,
            default => null,
        };
    }

    public function hasRole(string ...$roles): bool
    {
        return in_array($this->getRole(), $roles, true);
    }

    /**
     * @throws ApiException 403 when the caller has none of the roles
     */
    public function requireRole(string ...$roles): void
    {
        if (!$this->hasRole(...$roles)) {
            throw new ApiException(ErrorCode::FORBIDDEN, 'Your role does not allow this action.', 403);
        }
    }

    public function requireParent(): void
    {
        $this->requireRole(...self::PARENT_ROLES);
    }

    /** Forgets the cached member (e.g. after the identity changes in tests/console). */
    public function reset(): void
    {
        $this->member = null;
        $this->memberFor = null;
    }
}
