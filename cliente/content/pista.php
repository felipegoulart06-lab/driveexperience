<?php

declare(strict_types=1);

/**
 * Textos e fotos enviados pela equipe. As placas dos prints não entram aqui.
 */
return [
    'intro' => 'Experiências que começam com uma chave e terminam com vontade de repetir.',
    'people' => [
        ['file' => 'mulher-palmeiras.jpg', 'alt' => 'Cliente ao lado do conversível com o logo Drive Xperience, entre palmeiras', 'caption' => 'Drive Xperience', 'vehicles' => []],
        ['file' => 'casal.jpg', 'alt' => 'Casal apoiado no Porsche conversível preto', 'caption' => 'Porsche 911', 'vehicles' => ['porsche-911-cabriolet-turbo-s']],
        ['file' => 'porsche-preto.jpg', 'alt' => 'Cliente de conjunto claro apoiada no Porsche conversível preto', 'caption' => 'Porsche 911', 'vehicles' => ['porsche-911-cabriolet-turbo-s']],
        ['file' => 'porsche-azul.jpg', 'alt' => 'Cliente de conjunto vinho apoiada na traseira do Porsche azul', 'caption' => 'Porsche 911', 'vehicles' => ['porsche-911-cabriolet-turbo-s']],
        ['file' => 'homem-turbo.jpg', 'alt' => 'Cliente ao telefone apoiado no Porsche 911 conversível preto', 'caption' => 'Porsche 911 Turbo S', 'vehicles' => ['porsche-911-cabriolet-turbo-s']],
        ['file' => 'passageira.jpg', 'alt' => 'Passageira no banco do Porsche, de top preto e jeans', 'caption' => 'No Porsche', 'vehicles' => ['porsche-911-cabriolet-turbo-s', 'porsche-718-boxster-s']],
        ['file' => 'interior-bege.jpg', 'alt' => 'Cliente ao volante, interior bege do Porsche', 'caption' => 'Ao volante', 'vehicles' => ['porsche-718-boxster-s']],
    ],
    'routes' => [
        'super-drive' => [
            'file' => 'mapa-super-drive.jpg',
            'title' => 'Super DRIVE',
            'tagline' => 'Nossa experiência mais completa!',
            'time' => '1h30 a 1h45',
            'minutes' => 105,
            'stops' => [
                ['Embarque', 'Loja Boca Mafra.'],
                ['Estrada da Rainha', 'A descida clássica pela Rua Torta de BC, com a melhor vista da orla.'],
                ['Roda Gigante', 'Passagem pela icônica Big Wheel.'],
                ['Avenida Brasil', 'Percorrendo todo o centro comercial da cidade.'],
                ['Tour de Atrações', 'Rota exclusiva passando pelas marinas, pelo teleférico e pela área do Barco Pirata.'],
                ['Avenida Atlântica', 'Circuito percorrendo toda a extensão da orla mais famosa de SC, com a Marina Tedesco.'],
            ],
        ],
        'drive-xperience' => [
            'file' => 'mapa-rota-drive.jpg',
            'title' => 'Rota DRIVE',
            'tagline' => 'Ideal para quem deseja viver a primeira experiência com um supercarro.',
            'time' => '25 a 40 min',
            'minutes' => 40,
            'stops' => [
                ['Embarque', 'Loja Boca Mafra.'],
                ['Praia Brava', 'Passeio completo pela orla, um dos pontos mais badalados da região.'],
                ['Estrada da Rainha', 'Descida icônica pela Rua Torta de Balneário Camboriú, com uma vista magnífica da orla.'],
                ['Roda Gigante', 'Passagem por este cartão-postal imperdível.'],
            ],
        ],
        'rota-da-fama' => [
            'file' => 'mapa-rota-fama.jpg',
            'title' => 'Rota da FAMA',
            'tagline' => 'A experiência favorita dos turistas.',
            'time' => '45 min a 1h15',
            'minutes' => 75,
            'stops' => [
                ['Embarque', 'Loja Boca Mafra.'],
                ['Estrada da Rainha', 'A descida clássica pela Rua Torta de BC, com a melhor vista da orla.'],
                ['Roda Gigante', 'Passagem pela icônica Big Wheel.'],
                ['Avenida Brasil', 'Percorrendo todo o centro comercial da cidade.'],
                ['Tour de Atrações', 'Rota exclusiva passando pelas marinas, pelo teleférico e pela área do Barco Pirata.'],
                ['Avenida Atlântica', 'Circuito percorrendo toda a extensão da orla mais famosa de SC.'],
            ],
        ],
    ],
    'vehicles' => [
        'porsche-718-boxster-s' => [
            'capacity' => 2,
            'sheet' => 'ficha-boxster.jpg',
            'description' => 'Com o Porsche 718 Boxster S, cada curva vira espetáculo. O motor responde com 350 cv, o câmbio PDK entrega trocas instantâneas e o teto conversível oferece liberdade absoluta. De 0 a 100 km/h em 4,4s, essa é a essência da marca Porsche.',
            'notes' => '350 cv · 285 km/h · 0 a 100 em 4,4s · 2 lugares · teto conversível · câmbio automático PDK',
            'specs' => [
                ['Potência', '350 cv'],
                ['Lugares', '2 lugares'],
                ['Velocidade máxima', '285 km/h'],
                ['Teto', 'Conversível'],
                ['0 a 100 km/h', '4,4s'],
                ['Câmbio', 'Automático PDK'],
            ],
        ],
        'camaro-ss-convertible' => [
            'capacity' => 4,
            'sheet' => 'ficha-camaro.jpg',
            'description' => 'Sinta a potência bruta do Camaro SS Conversível com o vento no rosto e o som inconfundível do V8 ecoando a cada aceleração. Famoso por interpretar o Bumblebee nos filmes Transformers, este Camaro é a edição oficial Transformers, com detalhes exclusivos que homenageiam a franquia. São 406 cv, tração traseira e um design agressivo que transforma qualquer trajeto em uma verdadeira experiência de muscle car americano.',
            'notes' => '406 cv · 250 km/h · 0 a 100 em 4,7s · 4 lugares · teto conversível · câmbio automático de 10 marchas',
            'specs' => [
                ['Potência', '406 cv'],
                ['Lugares', '4 lugares'],
                ['Velocidade máxima', '250 km/h'],
                ['Teto', 'Conversível'],
                ['0 a 100 km/h', '4,7s'],
                ['Câmbio', 'Automático (10 marchas)'],
            ],
        ],
        'dodge-charger-rt' => [
            'capacity' => 5,
            'sheet' => 'ficha-dodge.jpg',
            'description' => 'Nada se compara ao ronco de um V8. O Dodge Charger R/T entrega 370 cv, chega a 230 km/h e coloca você de 0 a 100 km/h em 5,4s. Um muscle car com presença, potência e atitude em cada arrancada.',
            'notes' => '370 cv · 230 km/h · 0 a 100 em 5,4s · 5 lugares · teto solar · câmbio automático de 8 marchas',
            'specs' => [
                ['Potência', '370 cv'],
                ['Lugares', '5 lugares'],
                ['Velocidade máxima', '230 km/h'],
                ['Teto', 'Solar'],
                ['0 a 100 km/h', '5,4s'],
                ['Câmbio', 'Automático (8 marchas)'],
            ],
        ],
        'lamborghini-gallardo' => [
            'capacity' => 2,
            'sheet' => 'ficha-gallardo.jpg',
            'description' => 'O design que transformou a Lamborghini em ícone. Motor V10 de aspiração natural com aquele giro alto inconfundível, com +700 cv e escapamento esportivo, deixando o ronco ainda mais agressivo, alcança 325 km/h e vai de 0 a 100 em apenas 3,5s. Tração integral, postura baixa e visual de carro de pôster.',
            'notes' => '+700 cv · 325 km/h · 0 a 100 em 3,5s · 2 lugares · teto conversível · câmbio automático E-Gear',
            'specs' => [
                ['Potência', '+700 cv'],
                ['Lugares', '2 lugares'],
                ['Velocidade máxima', '325 km/h'],
                ['Teto', 'Conversível'],
                ['0 a 100 km/h', '3,5s'],
                ['Câmbio', 'Automático (E-Gear)'],
            ],
        ],
        'porsche-911-cabriolet-turbo-s' => [
            'capacity' => 4,
            'sheet' => 'ficha-911.jpg',
            'description' => 'A joia da frota. Já saía de fábrica como uma das 911 mais potentes do mundo, e a nossa foi além: com remap, entrega 700 cv. Escapamento esportivo com um ronco mais encorpado, daqueles que você sente no peito. Conversível, pra você pilotar de capota aberta sentindo cada acelerada.',
            'notes' => '700 cv · 318 km/h · 0 a 100 em 2,9s · 4 lugares · teto conversível · PDK de 7 marchas',
            'specs' => [
                ['Potência', '700 cv'],
                ['Lugares', '4 lugares'],
                ['Velocidade máxima', '318 km/h'],
                ['Teto', 'Conversível'],
                ['0 a 100 km/h', '2,9s'],
                ['Câmbio', 'PDK de 7 marchas'],
            ],
        ],
        'maserati-grancabrio' => [
            'capacity' => 4,
            'sheet' => 'ficha-maserati.jpg',
            'description' => 'Elegância italiana com coração de Ferrari. O motor V8 foi desenvolvido pela Ferrari, são 440 cv e uma velocidade máxima de 285 km/h. Som único, refinado e potente, complementado pelo escapamento esportivo. Conversível, espaçosa e luxuosa, é o supercarro pra quem quer requinte e performance na mesma pilotada.',
            'notes' => '440 cv · 285 km/h · 0 a 100 em 5,4s · 4 lugares · teto conversível · câmbio automático de 6 marchas',
            'specs' => [
                ['Potência', '440 cv'],
                ['Lugares', '4 lugares'],
                ['Velocidade máxima', '285 km/h'],
                ['Teto', 'Conversível'],
                ['0 a 100 km/h', '5,4s'],
                ['Câmbio', 'Automático (6 marchas)'],
            ],
        ],
    ],
];
