<?php

/**
-----------------------------------------------------------------------------

Fly Now Pay Later PHP developer homework assignement.

  In order to complete the homework assignement please follow these steps:

TASKS:

  1. Create a new Laravel project
  2. Setup the project as ready for development
  3. Add this file to `tests/Feature` folder
  4. Execute `php artisan test`
  5. Once the command returns that all tests are passed you may submit a link
  to the public git repository where we can review your codebase.

RULES:

  1. It is not allowed to make any changes to this TEST file.
  2. You must use Laravel 7 or newer
  3. You must use PHP 7.3 or newer
  4. You must use MySQL 5.7
  5. You must follow the PSR-2 standards
  
-----------------------------------------------------------------------------
**/

namespace Tests\Feature;

use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Testing\TestResponse;
use Illuminate\Http\Response;
use Tests\TestCase;

/**
 * Class FlyNowPayLaterTest
 * @package Tests\Feature
 */
class FlyNowPayLaterTest extends TestCase
{
    use WithFaker;

    /**
     * Configure the access key association with a USER model record
     * @var string
     */
    private $accessKey;

    /**
     * Configure the access secret association with a USER model record
     * @var string
     */
    private $accessSecret;

    /**
     * Configures the authorised access token based on the issued access credentials
     * @var string|null
     */
    private $accessToken = null;

    /**
     * Used to run test, do not touch
     * @var array
     */
    private static $createdAccessTokens = [];

    /**
     * Used to run test, do not touch
     * @var array
     */
    private $newFlightRecordContext = [];

    /**
     * Used to run test, do not touch
     * @var array
     */
    private $newPassengerRecordContext = [];

    public function setUp(): void
    {
        parent::setUp();

        $this->accessKey = config('services.my-api.access-key');
        $this->accessSecret = config('services.my-api.access-secret');

        $this->newFlightRecordContext = [
            'from' => [
                'date' => now()->addWeek()->format('Y-m-d'),
            ],
            'to' => [
                'date' => now()->addWeek()->addDay()->format('Y-m-d'),
            ],
            'leg' => [
                [
                    'iata' => 'LGW',
                    'order' => 1,
                ],
                [
                    'iata' => 'IST',
                    'order' => 2,
                ],
                [
                    'iata' => 'SVO',
                    'order' => 3,
                ],
                [
                    'iata' => 'SGN',
                    'order' => 4,
                ],
            ],
        ];

        $this->newPassengerRecordContext = [
            'firstName' => $this->faker->firstName,
            'lastName' => $this->faker->lastName,
            'dateOfBirth' => $this->faker->dateTimeBetween('-70 years', '-16 years')->format('Y-m-d'),
        ];
    }

    public function tearDown(): void
    {
        parent::tearDown();

        $this->checkAccessTokenRecord();
        $this->addAccessTokenRecord();
        $this->accessToken = null;
    }

    // Test cases

    public function testAuthenticationSuccess(): void
    {
        $response = $this->post('authorise', [
            'key' => $this->accessKey,
            'secret' => $this->accessSecret,
        ]);

        $response
            ->assertStatus(Response::HTTP_OK)
            ->assertJsonStructure([
                'accessToken'
            ]);
        $data = $response->json();

        $this->accessToken = $data['accessToken'];

        $this->assertNotEmpty($this->accessToken);
        $this->assertIsString($this->accessToken);
    }

    public function testAuthenticationErrorAtKey(): void
    {
        $response = $this->post('authorise', [
            'secret' => $this->accessKey,
        ]);

        $response
            ->assertStatus(Response::HTTP_BAD_REQUEST)
            ->assertExactJson([
                'message' => 'Missing authentication key'
            ]);
    }

    public function testAuthenticationErrorAtSecret(): void
    {
        $response = $this->post('authorise', [
            'key' => $this->accessSecret,
        ]);

        $response
            ->assertStatus(Response::HTTP_BAD_REQUEST)
            ->assertExactJson([
                'message' => 'Missing authentication secret'
            ]);
    }

    public function testCreateFlightSuccess(): void
    {
        $flightRecordId = $this->post('flight', $this->newFlightRecordContext, $this->getAuthenticationHeader())
            ->assertStatus(Response::HTTP_OK)
            ->assertJsonStructure([
                'flightRecordId',
            ])
            ->json('flightRecordId');

        $this->assertNotEmpty($flightRecordId);
        $this->assertIsString($flightRecordId);
    }

    public function testCreateFlightErrorAtMissingToken(): void
    {
        $this->post('flight', $this->newFlightRecordContext)
            ->assertStatus(Response::HTTP_UNAUTHORIZED)
            ->assertExactJson([
                'message' => 'You are not authorised to perform this action.',
            ]);
    }

    public function testCreateFlightErrorAtEmptyToken(): void
    {
        $this->post('flight', $this->newFlightRecordContext, [
            'accessToken' => null,
        ])
            ->assertStatus(Response::HTTP_UNAUTHORIZED)
            ->assertExactJson([
                'message' => 'You are not authorised to perform this action.',
            ]);
    }

    public function testCreateFlightErrorAtInvalidFromDate(): void
    {
        $this->newFlightRecordContext['from']['date'] = '0000-00-00';

        $this->post('flight', $this->newFlightRecordContext, $this->getAuthenticationHeader())
            ->assertStatus(Response::HTTP_BAD_REQUEST)
            ->assertExactJson([
                'message' => 'Outbound date is invalid.',
            ]);
    }

    public function testCreateFlightErrorAtFromDate(): void
    {
        $this->newFlightRecordContext['from']['date'] = '0000-00-00';

        $this->post('flight', $this->newFlightRecordContext, $this->getAuthenticationHeader())
            ->assertStatus(Response::HTTP_BAD_REQUEST)
            ->assertExactJson([
                'message' => 'Outbound date cannot be in past.',
            ]);
    }

    public function testCreateFlightErrorAtToDate(): void
    {
        $this->newFlightRecordContext['to']['date'] = now()->addWeek()->subDay()->format('Y-m-d');

        $this->post('flight', $this->newFlightRecordContext, $this->getAuthenticationHeader())
            ->assertStatus(Response::HTTP_BAD_REQUEST)
            ->assertExactJson([
                'message' => 'Inbound date cannot be prior outbound date.',
            ]);
    }

    public function testCreateFlightErrorAtLegOrder(): void
    {
        $this->newFlightRecordContext['leg'][rand(0, count($this->newFlightRecordContext['leg']) - 1)]['order'] = 100;
        $this->post('flight', $this->newFlightRecordContext, $this->getAuthenticationHeader())
            ->assertStatus(Response::HTTP_BAD_REQUEST)
            ->assertExactJson([
                'message' => 'Flight record has an error in the order index.',
            ]);
    }

    public function testCreateFlightErrorAtIataCode(): void
    {
        $leg = rand(0, count($this->newFlightRecordContext['leg']) - 1);
        $this->newFlightRecordContext['leg'][$leg]['iata'] .= 'X';

        $this->post('flight', $this->newFlightRecordContext, $this->getAuthenticationHeader())
            ->assertStatus(Response::HTTP_BAD_REQUEST)
            ->assertExactJson([
                'message' => sprintf('Provided IATA code for leg index ' . $this->newFlightRecordContext['leg'][$leg]['iata'] . ' is not valid.'),
            ]);
    }

    public function testGetFlightsSuccess(): void
    {
        $flightRecordId = $this->post('flight', $this->newFlightRecordContext, [
            'accessToken' => $this->getAccessToken(),
        ])->json('flightRecordId');

        $this->get('flights', $this->getAuthenticationHeader())
            ->assertStatus(Response::HTTP_OK)
            ->assertJsonFragment([
                [
                    'flightRecordId' => $flightRecordId,
                    'title' => 'Flying from LGW to SGN',
                    'lengthOfFlight' => '1 day',
                    'connectingFlights' => 'Flying from LGW to IST, then to SVO and finally to SGN.',
                    'passengers' => [
                        ''
                    ]
                ]
            ]);
    }

    public function testGetFlightsErrorAtMissingToken(): void
    {
        $this->get('flights')
            ->assertStatus(Response::HTTP_UNAUTHORIZED)
            ->assertExactJson([
                'message' => 'You are not authorised to perform this action.',
            ]);
    }

    public function testGetFlightSuccess(): void
    {
        $flightRecordId = $this->post('flight', $this->newFlightRecordContext, $this->getAuthenticationHeader())
            ->json('flightRecordId');

        $this->get('flight/' . $flightRecordId, [
            'accessToken' => $this->accessToken,
        ])
            ->assertStatus(Response::HTTP_OK)
            ->assertExactJson([
                'flightRecordId' => $flightRecordId,
                'title' => 'Flying from LGW to SGN',
                'lengthOfFlight' => '1 day',
                'connectingFlights' => 'Flying from LGW to IST, then to SVO and finally to SGN.',
                'passengers' => [
                    ''
                ],
            ]);
    }

    public function testGetFlightErrorWithoutToken(): void
    {
        $flightRecordId = $this->post('flight', $this->newFlightRecordContext, $this->getAuthenticationHeader())
            ->json('flightRecordId');

        $this->get('flight/' . $flightRecordId)
            ->assertStatus(Response::HTTP_UNAUTHORIZED)
            ->assertExactJson([
                'message' => 'You are not authorised to perform this action.',
            ]);
    }

    public function testGetFlightErrorAtInvalidId(): void
    {
        $this->get('flight/hello-world', $this->getAuthenticationHeader())
            ->assertStatus(Response::HTTP_NOT_FOUND);
    }

    public function testCreatePassengerWithoutFlightSuccess(): void
    {
        $this->post('passenger', $this->newPassengerRecordContext, $this->getAuthenticationHeader())
            ->assertStatus(Response::HTTP_OK)
            ->assertJsonStructure([
                'passengerRecordId',
            ]);
    }

    public function testCreatePassengerWithoutFlightRecordErrorAtMissingToken(): void
    {
        $this->post('passenger', $this->newPassengerRecordContext)
            ->assertStatus(Response::HTTP_UNAUTHORIZED)
            ->assertExactJson([
                'message' => 'You are not authorised to perform this action.',
            ]);
    }

    public function testCreatePassengerWithoutFlightRecordErrorAtFirstName(): void
    {
        $this->newPassengerRecordContext['firstName'] = '¯\_(ツ)_/¯';
        $this->post('passenger', $this->newPassengerRecordContext, $this->getAuthenticationHeader())
            ->assertStatus(Response::HTTP_BAD_REQUEST)
            ->assertJsonStructure([
                'message' => 'Passenger first name is not valid.',
            ]);
    }

    public function testCreatePassengerWithoutFlightRecordErrorAtLastName(): void
    {
        $this->newPassengerRecordContext['lastName'] = '¯\_(ツ)_/¯';
        $this->post('passenger', $this->newPassengerRecordContext, $this->getAuthenticationHeader())
            ->assertStatus(Response::HTTP_BAD_REQUEST)
            ->assertJsonStructure([
                'message' => 'Passenger last name is not valid.',
            ]);
    }

    public function testCreatePassengerWithoutFlightRecordErrorAtDateOfBirth(): void
    {
        $this->newPassengerRecordContext['dateOfBirth'] = now('00:00:00')->addWeek()->format('Y-m-d');
        $this->post('passenger', $this->newPassengerRecordContext, $this->getAuthenticationHeader())
            ->assertStatus(Response::HTTP_BAD_REQUEST)
            ->assertJsonStructure([
                'message' => 'Passenger date of birth cannot be in future.',
            ]);
    }

    public function testCreatePassengerWithFlightSuccess(): void
    {
        $flightRecordId = $this->post('flight', $this->newFlightRecordContext, [
            'accessToken' => $this->accessToken,
        ])->json('flightRecordId');

        $this->newPassengerRecordContext['flight'] = $flightRecordId;

        $this->post('passenger', $this->newPassengerRecordContext, $this->getAuthenticationHeader())
            ->assertStatus(Response::HTTP_OK)
            ->assertJsonStructure([
                'passengerRecordId',
            ]);
    }

    public function testCreatePassengerWithFlightRecordErrorAtMissingToken(): void
    {
        $flightRecordId = $this->post('flight', $this->newFlightRecordContext, [
            'accessToken' => $this->accessToken,
        ])->json('flightRecordId');

        $this->newPassengerRecordContext['flight'] = $flightRecordId;

        $this->post('passenger', $this->newPassengerRecordContext)
            ->assertStatus(Response::HTTP_UNAUTHORIZED)
            ->assertExactJson([
                'message' => 'You are not authorised to perform this action.',
            ]);
    }

    public function testCreatePassengerWithFlightRecordErrorAtFirstName(): void
    {
        $flightRecordId = $this->post('flight', $this->newFlightRecordContext, [
            'accessToken' => $this->accessToken,
        ])->json('flightRecordId');

        $this->newPassengerRecordContext['flight'] = $flightRecordId;
        $this->newPassengerRecordContext['firstName'] = '¯\_(ツ)_/¯';

        $this->post('passenger', $this->newPassengerRecordContext, $this->getAuthenticationHeader())
            ->assertStatus(Response::HTTP_BAD_REQUEST)
            ->assertJsonStructure([
                'message' => 'Passenger first name is not valid.',
            ]);
    }

    public function testCreatePassengerWithFlightRecordErrorAtLastName(): void
    {
        $flightRecordId = $this->post('flight', $this->newFlightRecordContext, [
            'accessToken' => $this->accessToken,
        ])->json('flightRecordId');

        $this->newPassengerRecordContext['flight'] = $flightRecordId;
        $this->newPassengerRecordContext['lastName'] = '¯\_(ツ)_/¯';

        $this->post('passenger', $this->newPassengerRecordContext, $this->getAuthenticationHeader())
            ->assertStatus(Response::HTTP_BAD_REQUEST)
            ->assertJsonStructure([
                'message' => 'Passenger last name is not valid.',
            ]);
    }

    public function testCreatePassengerWithFlightRecordErrorAtDateOfBirth(): void
    {
        $flightRecordId = $this->post('flight', $this->newFlightRecordContext, [
            'accessToken' => $this->accessToken,
        ])->json('flightRecordId');

        $this->newPassengerRecordContext['flight'] = $flightRecordId;
        $this->newPassengerRecordContext['dateOfBirth'] = now('00:00:00')->addWeek()->format('Y-m-d');

        $this->post('passenger', $this->newPassengerRecordContext, $this->getAuthenticationHeader())
            ->assertStatus(Response::HTTP_BAD_REQUEST)
            ->assertJsonStructure([
                'message' => 'Passenger date of birth cannot be in future.',
            ]);
    }

    public function testGetPassengersSuccess(): void
    {
        $flightRecordId = $this->post('flight', $this->newFlightRecordContext, [
            'accessToken' => $this->accessToken,
        ])->json('flightRecordId');

        $this->newPassengerRecordContext['firstName'] = 'Evan';
        $this->newPassengerRecordContext['firstName'] = 'Lu';
        $this->newPassengerRecordContext['dateOfBirth'] = '2003-01-01';
        $this->newPassengerRecordContext['flight'] = $flightRecordId;

        $passengerRecordId = $this->post('passenger', $this->newPassengerRecordContext, $this->getAuthenticationHeader());

        $this->get('passengers', $this->getAuthenticationHeader())
            ->assertStatus(Response::HTTP_OK)
            ->assertJsonFragment([
                [
                    'passengerRecordId' => $passengerRecordId,
                    'firstName' => 'Evan',
                    'lastName' => 'Lu',
                    'dateOfBirth' => '2003-01-01',
                    'flights' => [
                        [
                            'flightId' => $flightRecordId
                        ]
                    ]
                ]
            ]);
    }

    public function testGetPassengersErrorAtMissingToken(): void
    {
        $this->get('passengers')
            ->assertStatus(Response::HTTP_UNAUTHORIZED)
            ->assertExactJson([
                'message' => 'You are not authorised to perform this action.',
            ]);
    }

    public function testGetPassengerWithoutFlightSuccess(): void
    {
        $flightRecordId = $this->post('flight', $this->newFlightRecordContext, [
            'accessToken' => $this->accessToken,
        ])->json('flightRecordId');

        $this->newPassengerRecordContext['firstName'] = 'Evan';
        $this->newPassengerRecordContext['firstName'] = 'Lu';
        $this->newPassengerRecordContext['dateOfBirth'] = '2003-01-01';
        $this->newPassengerRecordContext['flight'] = $flightRecordId;

        $passengerRecordId = $this->post('passenger', $this->newPassengerRecordContext, $this->getAuthenticationHeader());

        $this->get('passenger/' . $passengerRecordId, $this->getAuthenticationHeader())
            ->assertStatus(Response::HTTP_OK)
            ->assertExactJson([
                'firstName' => 'Evan',
                'lastName' => 'Lu',
                'dateOfBirth' => '2003-01-01',
                'flights' => []
            ]);
    }

    public function testGetPassengerWithoutFlightErrorAtMissingToken(): void
    {
        $passengerRecordId = $this->post('passenger', $this->newPassengerRecordContext, $this->getAuthenticationHeader());

        $this->get('passenger/' . $passengerRecordId)
            ->assertStatus(Response::HTTP_UNAUTHORIZED)
            ->assertExactJson([
                'message' => 'You are not authorised to perform this action.',
            ]);
    }

    public function testGetPassengerWithoutFlightErrorAtInvalidId(): void
    {
        $this->get('passenger/hello-world')
            ->assertStatus(Response::HTTP_NOT_FOUND);
    }

    public function testGetPassengerWithFlightSuccess(): void
    {
        $flightRecordId = $this->post('flight', $this->newFlightRecordContext, [
            'accessToken' => $this->accessToken,
        ])->json('flightRecordId');

        $this->newPassengerRecordContext['firstName'] = 'Evan';
        $this->newPassengerRecordContext['firstName'] = 'Lu';
        $this->newPassengerRecordContext['dateOfBirth'] = '2003-01-01';
        $this->newPassengerRecordContext['flight'] = $flightRecordId;

        $passengerRecordId = $this->post('passenger', $this->newPassengerRecordContext, $this->getAuthenticationHeader());

        $this->get('passenger/' . $passengerRecordId, $this->getAuthenticationHeader())
            ->assertStatus(Response::HTTP_OK)
            ->assertExactJson([
                'firstName' => 'Evan',
                'lastName' => 'Lu',
                'dateOfBirth' => '2003-01-01',
                'flights' => [
                    [
                        'flightId' => $flightRecordId
                    ]
                ]
            ]);
    }

    public function testGetPassengerWithFlightErrorAtMissingToken(): void
    {
        $passengerRecordId = $this->post('passenger', $this->newPassengerRecordContext, $this->getAuthenticationHeader());

        $this->get('passenger/' . $passengerRecordId)
            ->assertStatus(Response::HTTP_UNAUTHORIZED)
            ->assertExactJson([
                'message' => 'You are not authorised to perform this action.',
            ]);
    }

    public function testGetPassengerWithFlightErrorAtInvalidId(): void
    {
        $this->get('passenger/hello-world')
            ->assertStatus(Response::HTTP_NOT_FOUND);
    }


    /**
     * Visit the given URI with a GET request.
     *
     * @param  string  $uri
     * @param  array  $headers
     * @return TestResponse
     */
    public function get($uri, array $headers = [])
    {
        $uri = sprintf('api/v1/%s', $uri);

        return parent::get($uri, $headers);
    }

    /**
     * Visit the given URI with a POST request.
     *
     * @param  string  $uri
     * @param  array  $data
     * @param  array  $headers
     * @return TestResponse
     */
    public function post($uri, array $data = [], array $headers = []): TestResponse
    {
        $uri = sprintf('api/v1/%s', $uri);

        return parent::post($uri, $data, $headers);
    }


    private function getAccessToken(): string
    {
        if (null === $this->accessToken) {
            $this->obtainAndSetAccessToken();
        }

        return $this->accessToken;
    }

    private function obtainAndSetAccessToken(): void
    {
        $this->accessToken = $this->post('authorise', [
            'key' => $this->accessKey,
            'secret' => $this->accessSecret,
        ])->json('accessToken');
    }

    private function getAuthenticationHeader(): array
    {
        $this->obtainAndSetAccessToken();

        return [
            'accessToken' => $this->accessToken
        ];
    }

    private function addAccessTokenRecord(): void
    {
        if (!is_null($this->accessToken)) {
            self::$createdAccessTokens[] = $this->accessToken;
        }
    }

    private function checkAccessTokenRecord(): void
    {
        $this->assertNotContains($this->accessToken, self::$createdAccessTokens);
    }
}
