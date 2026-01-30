<?php
namespace josemmo\Verifactu\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Promise\PromiseInterface;
use josemmo\Verifactu\Models\ComputerSystem;
use josemmo\Verifactu\Models\Records\FiscalIdentifier;
use UXML\UXML;

/**
 * Class to communicate with the AEAT web service endpoint for VERI*FACTU
 */
class AeatClientQuery extends AeatClient {
    /** Client XML namespace */
    public const NS_AEAT_CON = 'https://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/tike/cont/ws/ConsultaLR.xsd';
    public const NS_AEAT_SUM = 'https://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/tike/cont/ws/SuministroInformacion.xsd';

    private readonly FiscalIdentifier $taxpayer;
    private string $idVersion = '1.0';

    /**
     * Class constructor
     *
     * @param ComputerSystem                    $system     Computer system details
     * @param FiscalIdentifier                  $taxpayer   Taxpayer details (party that issues the invoices)
     * @param Client|null                       $httpClient Custom HTTP client, leave empty to create a new one
     * @param array{exercice: int, period: int} $period     Filter of period to query
     */
    public function __construct(
        ComputerSystem $system,
        FiscalIdentifier $taxpayer,
        ?Client $httpClient = null,
    ) {
        parent::__construct($system, $httpClient);
        $this->taxpayer = $taxpayer;
    }

    public function setIdVersion(string $version): static {
        $this->idVersion = $version;
        return $this;
    }

    /**
     * Builds the XML body of the request
     *
    // TODO: Transformar en una clase y documentar
     * @param array{exercice: int, period: int} $period Perido a filtrar
     *
     * @return UXML XML encoded request
     */
    public function createBody(array $period): UXML {
        $xml = UXML::newInstance('soapenv:Envelope', null, [
            'xmlns:soapenv' => self::NS_SOAPENV,
            'xmlns:con' => self::NS_AEAT_CON,
            'xmlns:sum' => self::NS_AEAT_SUM,
        ]);
        $xml->add('soapenv:Header');
        $baseElement = $xml->add('soapenv:Body')->add('sum:RegFactuSistemaFacturacion');

        // Add header
        $cabeceraElement = $baseElement->add('sum:Cabecera');
        $cabeceraElement->add('sum:IDVersion', $this->idVersion);

        $obligadoEmisionElement = $cabeceraElement->add('sum1:ObligadoEmision');
        $obligadoEmisionElement->add('sum1:NombreRazon', $this->taxpayer->name);
        $obligadoEmisionElement->add('sum1:NIF', $this->taxpayer->nif);

        $filterQuery = $baseElement->add('con:FiltroConsulta');
        $periodQuery = $filterQuery->add('con:PeriodoImputacion');

        // TODO: Transformar en una clase que devuelva el xml este ya formateado
        $periodQuery->add('sum:Ejercicio', (string)$period['exercice']);
        $periodQuery->add('sum:Peridodo', str_pad((string)$period['period'], 2, "0", STR_PAD_LEFT));

        return $xml;
    }

    /**
     * Send invoicing records
     *
     * @param UXML|array{exercice: int, period: int} $period Filter of period to query
     *
     * @return PromiseInterface<UXML> Response from service
     */
    public function send(UXML|array $period = null): PromiseInterface { /** @phpstan-ignore generics.notGeneric */
        if (is_array($period)) {
            $period = $this->createBody($period);
        }

        // Send request
        return $this->sendRequest($period);
        // TODO: Parse response to it's own thing
        // ->then();
    }
}
