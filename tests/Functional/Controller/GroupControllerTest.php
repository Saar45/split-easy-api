<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Entity\Appartenir;
use App\Entity\Depense;
use App\Entity\Groupe;
use App\Entity\Repartir;
use App\Entity\Utilisateur;
use App\Enum\RoleAppartenir;
use App\Enum\StatutInvitation;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class GroupControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        // Vide le pool cache.app (rate limiter) avant chaque test pour éviter le throttling cumulé.
        $cacheDir = dirname(__DIR__, 3).'/var/share/test/pools/app';
        if (is_dir($cacheDir)) {
            self::removeDir($cacheDir);
        }

        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->em->createQuery('DELETE FROM '.Repartir::class.' r')->execute();
        $this->em->createQuery('DELETE FROM '.Depense::class.' d')->execute();
        $this->em->createQuery('DELETE FROM '.Appartenir::class.' a')->execute();
        $this->em->createQuery('DELETE FROM '.Groupe::class.' g')->execute();
        $this->em->createQuery('DELETE FROM '.Utilisateur::class.' u')->execute();
    }

    private static function removeDir(string $dir): void
    {
        foreach (scandir($dir) ?: [] as $item) {
            if ('.' === $item || '..' === $item) {
                continue;
            }
            $path = $dir.'/'.$item;
            is_dir($path) ? self::removeDir($path) : unlink($path);
        }
        rmdir($dir);
    }

    public function testCreateGroupReturns201AndAutoAddsCreator(): void
    {
        $email = 'creator_'.uniqid().'@test.com';
        $this->register($email, 'SecurePass1');
        $token = $this->loginAndGetToken($email, 'SecurePass1');

        $this->jsonRequest('POST', '/api/groups', [
            'nom' => 'Vacances',
            'description' => 'Voyage en groupe',
            'couleur' => '#FF5733',
        ], $token);

        self::assertResponseStatusCodeSame(201);
        $body = json_decode($this->client->getResponse()->getContent(), true);
        self::assertSame('Vacances', $body['nom']);
        self::assertSame('#FF5733', $body['couleur']);
        self::assertArrayHasKey('id', $body);

        $groupe = $this->em->getRepository(Groupe::class)->find($body['id']);
        self::assertNotNull($groupe);

        $appartenir = $this->em->getRepository(Appartenir::class)->findOneBy([
            'groupe' => $groupe,
        ]);
        self::assertNotNull($appartenir);
        self::assertSame(RoleAppartenir::Createur, $appartenir->getRole());
        self::assertSame(StatutInvitation::Acceptee, $appartenir->getStatutInvitation());
    }

    public function testCreateGroupWithoutAuthReturns401(): void
    {
        $this->jsonRequest('POST', '/api/groups', ['nom' => 'Test'], null);

        self::assertResponseStatusCodeSame(401);
    }

    public function testCreateGroupWithEmptyNomReturns422(): void
    {
        $email = 'invalid_'.uniqid().'@test.com';
        $this->register($email, 'SecurePass1');
        $token = $this->loginAndGetToken($email, 'SecurePass1');

        $this->jsonRequest('POST', '/api/groups', [
            'nom' => '',
            'couleur' => '#FF5733',
        ], $token);

        self::assertResponseStatusCodeSame(422);
    }

    public function testCreateGroupWithBadCouleurFormatReturns422(): void
    {
        $email = 'bad_couleur_'.uniqid().'@test.com';
        $this->register($email, 'SecurePass1');
        $token = $this->loginAndGetToken($email, 'SecurePass1');

        $this->jsonRequest('POST', '/api/groups', [
            'nom' => 'Groupe valide',
            'couleur' => 'rouge',
        ], $token);

        self::assertResponseStatusCodeSame(422);
    }

    public function testListGroupsReturnsOnlyUserGroups(): void
    {
        $emailA = 'usera_'.uniqid().'@test.com';
        $emailB = 'userb_'.uniqid().'@test.com';

        $this->register($emailA, 'SecurePass1');
        $this->register($emailB, 'SecurePass1');

        $tokenA = $this->loginAndGetToken($emailA, 'SecurePass1');
        $tokenB = $this->loginAndGetToken($emailB, 'SecurePass1');

        $this->jsonRequest('POST', '/api/groups', ['nom' => 'Groupe A'], $tokenA);
        $this->jsonRequest('POST', '/api/groups', ['nom' => 'Groupe B'], $tokenB);

        $this->client->request('GET', '/api/groups', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$tokenA,
        ]);

        self::assertResponseIsSuccessful();
        $body = json_decode($this->client->getResponse()->getContent(), true);
        self::assertCount(1, $body);
        self::assertSame('Groupe A', $body[0]['nom']);
    }

    public function testShowGroupReturnsForbiddenWhenUserIsNotMember(): void
    {
        $emailA = 'owner_'.uniqid().'@test.com';
        $emailB = 'outsider_'.uniqid().'@test.com';

        $this->register($emailA, 'SecurePass1');
        $this->register($emailB, 'SecurePass1');

        $tokenA = $this->loginAndGetToken($emailA, 'SecurePass1');
        $tokenB = $this->loginAndGetToken($emailB, 'SecurePass1');

        $this->jsonRequest('POST', '/api/groups', ['nom' => 'Private Group'], $tokenA);
        $createBody = json_decode($this->client->getResponse()->getContent(), true);
        $groupId = $createBody['id'];

        $this->client->request('GET', '/api/groups/'.$groupId, server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$tokenB,
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testDeleteGroupReturns204ForCreator(): void
    {
        $email = 'del_creator_'.uniqid().'@test.com';
        $this->register($email, 'SecurePass1');
        $token = $this->loginAndGetToken($email, 'SecurePass1');

        $this->jsonRequest('POST', '/api/groups', ['nom' => 'To Delete'], $token);
        $body = json_decode($this->client->getResponse()->getContent(), true);
        $groupId = $body['id'];

        $this->client->request('DELETE', '/api/groups/'.$groupId, server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);

        self::assertResponseStatusCodeSame(204);
        self::assertEmpty($this->client->getResponse()->getContent());
    }

    public function testUpdateGroupReturnsUpdatedForCreator(): void
    {
        $email = 'upd_creator_'.uniqid().'@test.com';
        $this->register($email, 'SecurePass1');
        $token = $this->loginAndGetToken($email, 'SecurePass1');

        $this->jsonRequest('POST', '/api/groups', ['nom' => 'Before'], $token);
        $groupId = json_decode($this->client->getResponse()->getContent(), true)['id'];

        $this->jsonRequest('PUT', '/api/groups/'.$groupId, ['nom' => 'After', 'couleur' => '#FF9800'], $token);

        self::assertResponseIsSuccessful();
        $body = json_decode($this->client->getResponse()->getContent(), true);
        self::assertSame('After', $body['nom']);
        self::assertSame('#FF9800', $body['couleur']);
    }

    public function testUpdateGroupReturns403ForNonCreator(): void
    {
        $emailCreator = 'upd_owner_'.uniqid().'@test.com';
        $emailOther = 'upd_other_'.uniqid().'@test.com';
        $this->register($emailCreator, 'SecurePass1');
        $this->register($emailOther, 'SecurePass1');
        $tokenCreator = $this->loginAndGetToken($emailCreator, 'SecurePass1');
        $tokenOther = $this->loginAndGetToken($emailOther, 'SecurePass1');

        $this->jsonRequest('POST', '/api/groups', ['nom' => 'Locked'], $tokenCreator);
        $groupId = json_decode($this->client->getResponse()->getContent(), true)['id'];

        $this->jsonRequest('PUT', '/api/groups/'.$groupId, ['nom' => 'Hacked'], $tokenOther);

        self::assertResponseStatusCodeSame(403);
    }

    public function testUpdateGroupReturns422ForInvalidCouleur(): void
    {
        $email = 'upd_invalid_'.uniqid().'@test.com';
        $this->register($email, 'SecurePass1');
        $token = $this->loginAndGetToken($email, 'SecurePass1');

        $this->jsonRequest('POST', '/api/groups', ['nom' => 'Group'], $token);
        $groupId = json_decode($this->client->getResponse()->getContent(), true)['id'];

        $this->jsonRequest('PUT', '/api/groups/'.$groupId, ['couleur' => 'not-a-hex'], $token);

        self::assertResponseStatusCodeSame(422);
    }

    public function testDeleteGroupReturns403ForRegularMember(): void
    {
        $emailCreator = 'creator2_'.uniqid().'@test.com';
        $emailMember = 'member_'.uniqid().'@test.com';

        $this->register($emailCreator, 'SecurePass1');
        $this->register($emailMember, 'SecurePass1');

        $tokenCreator = $this->loginAndGetToken($emailCreator, 'SecurePass1');
        $tokenMember = $this->loginAndGetToken($emailMember, 'SecurePass1');

        $this->jsonRequest('POST', '/api/groups', ['nom' => 'Shared Group'], $tokenCreator);
        $body = json_decode($this->client->getResponse()->getContent(), true);
        $groupId = $body['id'];

        $userMember = $this->em->getRepository(Utilisateur::class)->findOneBy(['email' => $emailMember]);
        $groupe = $this->em->getRepository(Groupe::class)->find($groupId);

        $appartenir = (new Appartenir())
            ->setUtilisateur($userMember)
            ->setGroupe($groupe)
            ->setRole(RoleAppartenir::Membre)
            ->setStatutInvitation(StatutInvitation::Acceptee)
            ->setDateAdhesion(new \DateTimeImmutable())
            ->setTokenInvitation(bin2hex(random_bytes(32)));

        $this->em->persist($appartenir);
        $this->em->flush();

        $this->client->request('DELETE', '/api/groups/'.$groupId, server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$tokenMember,
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    private function jsonRequest(string $method, string $uri, array $payload, ?string $token): void
    {
        $server = ['CONTENT_TYPE' => 'application/json'];
        if (null !== $token) {
            $server['HTTP_AUTHORIZATION'] = 'Bearer '.$token;
        }

        $this->client->request(
            $method,
            $uri,
            server: $server,
            content: json_encode($payload, JSON_THROW_ON_ERROR),
        );
    }

    private function register(string $email, string $password): void
    {
        $server = ['CONTENT_TYPE' => 'application/json'];
        $this->client->request('POST', '/api/register', server: $server, content: json_encode([
            'nom' => 'Test',
            'prenom' => 'User',
            'email' => $email,
            'motDePasse' => $password,
            'cguAcceptees' => true,
        ], JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(201);
    }

    private function loginAndGetToken(string $email, string $password): string
    {
        $server = ['CONTENT_TYPE' => 'application/json'];
        $this->client->request('POST', '/api/login', server: $server, content: json_encode([
            'email' => $email,
            'motDePasse' => $password,
        ], JSON_THROW_ON_ERROR));
        self::assertResponseIsSuccessful();

        return json_decode($this->client->getResponse()->getContent(), true)['token'];
    }
}
