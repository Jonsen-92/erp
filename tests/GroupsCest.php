<?php
class GroupsCest 
{    
    public function listApi(ApiTester $I)
    {
        //positive testing
        $I->sendGET('/groups');
        $I->seeResponseCodeIs(200);
        $I->seeResponseIsJson();

        $schema = [
            "properties" => [
                "status_code" => [
                    "type" => "string"
                ],
                "status_message" => [
                    "type" => "string"
                ],
                "data" => [
                    "type" => "object",
                    "properties" => [
                        "groups" => [
                            "type" => "array",
                            "items" => [
                                "properties" => [
                                    "id" => [
                                        "type" => "integer",
                                        "minimum" => 1
                                    ],
                                    "name" => [
                                        "type" => "string"
                                    ],
                                    "created" => [
                                        "type" => "string",
                                        "format" => "datetime"
                                    ],
                                    "modified" => [
                                        "type" => "string",
                                        "format" => "datetime"
                                    ]
                                ]
                            ]
                        ],
                        "pagination" => [
                            "type" => "object",
                            "properties" => [
                                "page" => [
                                    "type" => "integer",
                                    "minimum" => 1,        
                                ],
                                "limit" => [
                                    "type" => "integer",
                                    "minimum" => 1,
                                ],
                                "order" => [
                                    "type" => "string",
                                    "enum" => ["id", "name"]
                                ],
                                "sort" => [
                                    "type" => "string",
                                    "enum" => ["asc", "desc"]
                                ],
                                "count" => [
                                    "type" => "integer"
                                ],
                                "keyword" => [
                                    "type" => ["string", "null"]
                                ]
                            ]
                        ]
                    ]

                ]
            ]

        ];

        $I->seeResponseIsValidOnJsonSchemaString(json_encode($schema));
    }

    public function addApi(ApiTester $I)
    {
        // positive testing
        $I->haveHttpHeader('accept', 'application/json');
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPOST('/groups', json_encode([
            "name" => "groups from api test"
        ]));
        $I->seeResponseCodeIs(201);
        $I->seeResponseIsJson();
        $I->seeResponseMatchesJsonType([
            'status_code' => 'string',
            'status_message' => 'string',
            'data' => 'array|null'
        ]);

        $I->seeResponseMatchesJsonType([
            'name' => 'string',
            'created' => 'string:date',
            'modified' => 'string:date',
            'id' => 'integer'
        ], '$.data');

        // negative testing
        $I->haveHttpHeader('accept', 'application/json');
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPOST('/groups', json_encode([
            "nama" => "groups from api test"
        ]));
         
        $I->seeResponseCodeIs(400);
        $I->seeResponseIsJson();

        $I->seeResponseContainsJson([
            "status_code" => "cdc-101",
            "status_message" => "error add new group",
            "data" => null
        ]);
    }

    public function viewApi(ApiTester $I)
    {
        // positive testing
        $I->sendGET('/groups/1');
        $I->seeResponseCodeIs(200);
        $I->seeResponseIsJson();

        $schema = [
            "properties" => [
                "status_code" => [
                    "type" => "string"
                ],
                "status_message" => [
                    "type" => "string"
                ],
                "data" => [
                    "type" => "object",
                    "properties" => [
                        "id" => [
                            "type" => "integer"
                        ],
                        "name" => [
                            "type" => "string"
                        ],
                        "created" => [
                            "type" => "string",
                            "format" => "datetime"
                        ],
                        "modified" => [
                            "type" => "string",
                            "format" => "datetime"
                        ]
                    ]
                ]
            ]
        ];

        $I->seeResponseIsValidOnJsonSchemaString(json_encode($schema));
    }
}