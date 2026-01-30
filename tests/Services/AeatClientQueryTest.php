<?php
namespace josemmo\Verifactu\Tests\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use josemmo\Verifactu\Exceptions\AeatException;
use josemmo\Verifactu\Models\ComputerSystem;
use josemmo\Verifactu\Models\Records\FiscalIdentifier;
use josemmo\Verifactu\Services\AeatClient;
use josemmo\Verifactu\Services\AeatClientQuery;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientExceptionInterface;

final class AeatClientQueryTest extends TestCase {
    /**
     * Get mocked AEAT client
     *
     * @param Response|ClientExceptionInterface $response Mocked response
     * @param array{exercice: int, period: int} $period   Filter of period to query
     *
     * @return AeatClient AEAT client instance
     */
    private function getMockedClient(Response|ClientExceptionInterface $response, array $period): AeatClient {
        // Create HTTP client mock
        $mock = new MockHandler([$response]);
        $httpClient = new Client([
            'handler' => HandlerStack::create($mock),
        ]);

        // Build computer system
        $system = new ComputerSystem();
        $system->vendorName = 'Perico de los Palotes, S.A.';
        $system->vendorNif = 'A00000000';
        $system->name = 'Test SIF';
        $system->id = 'XX';
        $system->version = '0.0.1';
        $system->installationNumber = 'ABC0123';
        $system->onlySupportsVerifactu = true;
        $system->supportsMultipleTaxpayers = true;
        $system->hasMultipleTaxpayers = false;
        $system->validate();

        // Build AEAT client
        $taxpayer = new FiscalIdentifier('Perico de los Palotes, S.A.', 'A00000000');
        $client = new AeatClientQuery(
            $system,
            $taxpayer,
            $period,
            $httpClient
        );

        return $client;
    }

    public function testThrowsExceptionForMalformedXmlResponse(): void {
        $this->expectException(AeatException::class);
        $this->expectExceptionMessage('Failed to parse XML response');
        $client = $this->getMockedClient(
            new Response(200, [], '<element>Malformed XML</notClosingElement>'),
            [
                'exercice' => 2025,
                'period' => 12,
            ]
        );
        $client->send()->wait();
    }

    public function testThrowsExceptionForUnexpectedXmlResponse(): void {
        $this->expectException(AeatException::class);
        $this->expectExceptionMessage('Missing <tikR:RespuestaRegFactuSistemaFacturacion /> element from response');
        $client = $this->getMockedClient(
            new Response(401, [], '<html><body>Unauthorized</body></html>'),
            [
                'exercice' => 2025,
                'period' => 12,
            ]
        );
        $client->send()->wait();
    }

    public function testThrowsExceptionOnConnectionError(): void {
        $this->expectException(ConnectException::class);
        $this->expectExceptionMessage('Exception message');
        $client = $this->getMockedClient(
            new ConnectException('Exception message', new Request('GET', 'test')),
            [
                'exercice' => 2025,
                'period' => 12,
            ]
        );
        $client->send()->wait();
    }
}
