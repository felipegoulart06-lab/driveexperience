<?php

declare(strict_types=1);

namespace App\Domain;

/**
 * Trajetos oficiais com pontos na ordem da corrida (lng, lat).
 * O Mapbox Directions encaixa esses pontos nas vias reais.
 */
final class ExperienceRoutes
{
    public static function forModality(string $slug): ?array
    {
        $key = self::alias($slug);
        $routes = self::definitions();
        return $routes[$key] ?? null;
    }

    public static function alias(string $slug): string
    {
        return match ($slug) {
            'carona' => 'drive-xperience',
            'carona-da-fama' => 'rota-da-fama',
            default => $slug,
        };
    }

    /**
     * @return array<string, array{
     *   title: string,
     *   tagline: string,
     *   time: string,
     *   about: string,
     *   stops: list<array{name: string, text: string}>,
     *   waypoints: list<array{name: string, lng: float, lat: float}>
     * }>
     */
    public static function definitions(): array
    {
        $embarque = ['name' => 'Embarque · Loja Boca Mafra', 'lng' => -48.63933, 'lat' => -26.96806];
        $rainha = ['name' => 'Estrada da Rainha', 'lng' => -48.63575, 'lat' => -26.97274];
        $ruaTorta = ['name' => 'Rua Torta', 'lng' => -48.63358, 'lat' => -26.97201];
        $roda = ['name' => 'Roda Gigante', 'lng' => -48.63185, 'lat' => -26.97092];
        $brava = ['name' => 'Praia Brava', 'lng' => -48.62906, 'lat' => -26.94550];
        $atlanticaNorte = ['name' => 'Avenida Atlântica · Pontal Norte', 'lng' => -48.63326, 'lat' => -26.97183];
        $atlantica = ['name' => 'Avenida Atlântica', 'lng' => -48.62927, 'lat' => -26.99054];
        $atlanticaSul = ['name' => 'Avenida Atlântica · Barra Sul', 'lng' => -48.61608, 'lat' => -27.00426];
        $brasil = ['name' => 'Avenida Brasil', 'lng' => -48.63424, 'lat' => -26.98751];
        $brasilSul = ['name' => 'Avenida Brasil · sul', 'lng' => -48.62579, 'lat' => -26.99844];
        $teleferico = ['name' => 'Teleférico Unipraias', 'lng' => -48.60359, 'lat' => -27.00504];
        $pirata = ['name' => 'Barco Pirata', 'lng' => -48.60338, 'lat' => -27.00551];
        $tedesco = ['name' => 'Marina Tedesco', 'lng' => -48.60848, 'lat' => -27.00707];

        return [
            'super-drive' => [
                'title' => 'Super DRIVE',
                'tagline' => 'A experiência mais completa.',
                'time' => '1h30 a 1h45',
                'about' => 'O Super DRIVE percorre Balneário Camboriú de ponta a ponta. A saída é na loja Boca Mafra, no Ariribá. O carro sobe a Estrada da Rainha, desce a Rua Torta, passa pela Roda Gigante e segue pela Avenida Atlântica até a Barra Sul, no teleférico, no Barco Pirata e na Marina Tedesco. A volta atravessa o centro pela Avenida Brasil e retorna à loja. Duração prevista de 1h30 a 1h45.',
                'stops' => [
                    ['name' => 'Embarque · Loja Boca Mafra', 'text' => 'O passeio começa na loja Boca Mafra Premium, na Avenida do Estado, no Ariribá. O cliente é recebido, a reserva é conferida e a saída acontece dali.'],
                    ['name' => 'Estrada da Rainha', 'text' => 'Subida pela Estrada da Rainha, no Pontal Norte. É o trecho alto do passeio, com a orla de Balneário Camboriú aberta à frente.'],
                    ['name' => 'Rua Torta', 'text' => 'Descida pela Rua Torta, a via sinuosa ao lado da Estrada da Rainha. As curvas acompanham o morro e abrem a vista da praia e da roda gigante.'],
                    ['name' => 'Roda Gigante', 'text' => 'Passagem pela FG Big Wheel, na Estrada da Rainha. A roda é o cartão-postal do Pontal Norte e marca a volta para a orla.'],
                    ['name' => 'Avenida Atlântica', 'text' => 'Percurso pela orla da Praia Central, do Pontal Norte até a Barra Sul. É a avenida mais conhecida da cidade, com a praia de um lado e os prédios do outro.'],
                    ['name' => 'Teleférico Unipraias', 'text' => 'Chegada ao Parque Unipraias, no fim da Avenida Atlântica. Dali sai o teleférico que sobe o morro em direção à Praia de Laranjeiras.'],
                    ['name' => 'Barco Pirata', 'text' => 'Passagem pela área do Barco Pirata, na Avenida Normando Tedesco. É um dos passeios de barco da Barra Sul, ao lado das marinas.'],
                    ['name' => 'Marina Tedesco', 'text' => 'Parada na Marina Tedesco, na Barra Sul. A marina fecha o trecho sul do Super DRIVE, antes de o carro voltar pelo centro.'],
                    ['name' => 'Avenida Brasil', 'text' => 'Retorno pela Avenida Brasil, atravessando o centro comercial da cidade, até a loja Boca Mafra.'],
                ],
                'waypoints' => [$embarque, $rainha, $ruaTorta, $roda, $atlanticaNorte, $atlantica, $atlanticaSul, $teleferico, $pirata, $tedesco, $brasilSul, $brasil, $embarque],
            ],
            'drive-xperience' => [
                'title' => 'Rota DRIVE',
                'tagline' => 'A primeira experiência com um supercarro.',
                'time' => '25 a 40 min',
                'about' => 'A Rota DRIVE é o passeio de entrada. Sai da loja Boca Mafra, segue pela orla da Praia Brava e volta pelo Pontal Norte, com a Estrada da Rainha, a Rua Torta e a Roda Gigante. Não desce até a Barra Sul. Duração prevista de 25 a 40 minutos.',
                'stops' => [
                    ['name' => 'Embarque · Loja Boca Mafra', 'text' => 'A saída é na loja Boca Mafra Premium, na Avenida do Estado, no Ariribá. O cliente embarca e o passeio segue para o norte, em direção à Praia Brava.'],
                    ['name' => 'Praia Brava', 'text' => 'Passeio pela orla da Praia Brava, na Avenida Doutor José Medeiros Vieira. É o trecho mais ao norte da rota, com a praia ao lado da pista.'],
                    ['name' => 'Estrada da Rainha', 'text' => 'Na volta, o carro sobe a Estrada da Rainha, no Pontal Norte. A descida é o trecho clássico da rota, com vista da orla.'],
                    ['name' => 'Rua Torta', 'text' => 'Descida pela Rua Torta, ao lado da Estrada da Rainha. As curvas do morro abrem a vista da praia antes de chegar à roda gigante.'],
                    ['name' => 'Roda Gigante', 'text' => 'Passagem pela FG Big Wheel e retorno à loja Boca Mafra. A roda fecha a Rota DRIVE.'],
                ],
                'waypoints' => [$embarque, $brava, $rainha, $ruaTorta, $roda, $embarque],
            ],
            'rota-da-fama' => [
                'title' => 'Rota da FAMA',
                'tagline' => 'A experiência favorita de quem visita a cidade.',
                'time' => '45 min a 1h15',
                'about' => 'A Rota da FAMA junta o morro do Pontal Norte, o centro e a orla. Sai da loja Boca Mafra, desce a Estrada da Rainha e a Rua Torta, passa pela Roda Gigante, atravessa a Avenida Brasil e segue até a Barra Sul, no teleférico e no Barco Pirata. A volta é pela Avenida Atlântica. Duração prevista de 45 min a 1h15.',
                'stops' => [
                    ['name' => 'Embarque · Loja Boca Mafra', 'text' => 'O passeio começa na loja Boca Mafra Premium, na Avenida do Estado. A reserva é conferida e o carro parte para o Pontal Norte.'],
                    ['name' => 'Estrada da Rainha', 'text' => 'Subida pela Estrada da Rainha. Do alto do Pontal Norte se vê a orla que o passeio ainda vai percorrer.'],
                    ['name' => 'Rua Torta', 'text' => 'Descida pela Rua Torta, a via sinuosa ao lado da Estrada da Rainha, com a praia e a roda gigante à vista.'],
                    ['name' => 'Roda Gigante', 'text' => 'Passagem pela FG Big Wheel, cartão-postal do Pontal Norte, antes de entrar na cidade.'],
                    ['name' => 'Avenida Brasil', 'text' => 'Travessia do centro comercial pela Avenida Brasil, entre a Praia Central e os bairros internos.'],
                    ['name' => 'Teleférico Unipraias', 'text' => 'Chegada à Barra Sul, no Parque Unipraias. O teleférico sobe o morro em direção à Praia de Laranjeiras.'],
                    ['name' => 'Barco Pirata', 'text' => 'Passagem pela área do Barco Pirata, na Avenida Normando Tedesco, junto das marinas da Barra Sul.'],
                    ['name' => 'Avenida Atlântica', 'text' => 'Retorno pela Avenida Atlântica, percorrendo a orla da Praia Central até a loja.'],
                ],
                'waypoints' => [$embarque, $rainha, $ruaTorta, $roda, $atlanticaNorte, $brasil, $brasilSul, $atlanticaSul, $teleferico, $pirata, $atlantica, $embarque],
            ],
        ];
    }
}
