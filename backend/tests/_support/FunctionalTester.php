<?php

declare(strict_types=1);

use app\models\ChildProfile;
use app\models\Family;
use app\models\FamilyMember;
use app\models\User;
use tests\_support\FamilyFactory;

/**
 * Actor for API tests.
 *
 * @SuppressWarnings(PHPMD)
 */
class FunctionalTester extends \Codeception\Actor
{
    use _generated\FunctionalTesterActions;

    /** Each test starts with an empty cache (rate-limit counters live there). */
    public function __construct(\Codeception\Scenario $scenario)
    {
        parent::__construct($scenario);
        \Yii::$app?->cache?->flush();
    }

    /**
     * Creates an active user and authenticates the next requests with its bearer token.
     */
    public function amAuthenticatedAs(?User $user = null): User
    {
        $user ??= FamilyFactory::user();
        $this->haveHttpHeader('Authorization', 'Bearer ' . $user->access_token);
        $this->haveHttpHeader('Content-Type', 'application/json');

        return $user;
    }

    /**
     * Authenticates as a (new) member of $family (default: a new family).
     */
    public function amFamilyMember(?Family $family = null, string $role = FamilyMember::ROLE_ADMIN): FamilyMember
    {
        $member = FamilyFactory::member($family ?? FamilyFactory::family(), FamilyFactory::user(), $role);
        $this->amAuthenticatedAs($member->user);

        return $member;
    }

    /**
     * Asserts the {data: null, meta: {errors: [...]}} envelope and returns the error codes.
     *
     * @return list<string>
     */
    public function seeErrorEnvelope(int $status, string $code): array
    {
        $this->seeResponseCodeIs($status);
        $this->seeResponseIsJson();
        $this->seeResponseContainsJson(['data' => null]);
        $this->seeResponseContainsJson(['meta' => ['errors' => [['code' => $code]]]]);

        /** @var list<string> $codes */
        $codes = $this->grabDataFromResponseByJsonPath('$.meta.errors[*].code');
        return $codes;
    }

    /** Authenticates the next requests as $child on a paired device. */
    public function amChild(ChildProfile $child): void
    {
        $this->haveHttpHeader('Authorization', 'Bearer ' . FamilyFactory::childToken($child));
        $this->haveHttpHeader('Content-Type', 'application/json');
    }
}
