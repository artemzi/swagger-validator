<?php

namespace WakeOnWeb\Component\Swagger\Tests;

use Psr\Http\Message\ResponseInterface;
use WakeOnWeb\Component\Swagger\Specification\PathItem;
use WakeOnWeb\Component\Swagger\Specification\Swagger;
use WakeOnWeb\Component\Swagger\SwaggerFactory;
use WakeOnWeb\Component\Swagger\Test\SwaggerValidator;

/**
 * @author Quentin Schuler <q.schuler@wakeonweb.com>
 */
class SwaggerValidatorTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @test
     */
    public function testValidateResponseFor()
    {
        $swagger = <<<JSON
{
    "swagger": "2.0",
    "info": {
        "title": "test",
        "version": "1.0"
    },
    "produces": [
        "application/json"
    ],
    "paths": {
        "/tests": {
            "get": {
                "responses": {
                    "200": {
                        "description": "Get the list of all the tests cases."
                    }
                }
            }
        }
    }
}
JSON;

        $prophecy = $this->prophesize(ResponseInterface::class);
        $prophecy->getStatusCode()->willReturn(200);
        $prophecy->getHeader('Content-Type')->willReturn(['application/json']);

        $validator = new SwaggerValidator($this->buildSwagger($swagger));
        $validator->validateResponseFor($prophecy->reveal(), PathItem::METHOD_GET, '/tests', 200);
    }

    /**
     * @test
     * @expectedException \WakeOnWeb\Component\Swagger\Test\Exception\StatusCodeException
     */
    public function testValidateResponseForThrowsAnExceptionWhenTheStatusCodeIsInvalid()
    {
        $swagger = <<<JSON
{
    "swagger": "2.0",
    "info": {
        "title": "test",
        "version": "1.0"
    },
    "produces": [
        "application/json"
    ],
    "paths": {
        "/tests": {
            "get": {
                "responses": {
                    "200": {
                        "description": "Get the list of all the tests cases."
                    }
                }
            }
        }
    }
}
JSON;

        $prophecy = $this->prophesize(ResponseInterface::class);
        $prophecy->getStatusCode()->willReturn(400);
        $prophecy->getHeader('Content-Type')->willReturn(['application/json']);

        $validator = new SwaggerValidator($this->buildSwagger($swagger));
        $validator->validateResponseFor($prophecy->reveal(), PathItem::METHOD_GET, '/tests', 200);
    }

    /**
     * @test
     * @expectedException \WakeOnWeb\Component\Swagger\Test\Exception\UnknownResponseCodeException
     */
    public function testValidateResponseForThrowsAnExceptionWhenTheStatusCodeIsNotOnTheSchema()
    {
        $swagger = <<<JSON
{
    "swagger": "2.0",
    "info": {
        "title": "test",
        "version": "1.0"
    },
    "produces": [
        "application/json"
    ],
    "paths": {
        "/tests": {
            "get": {
                "responses": {
                    "200": {
                        "description": "Get the list of all the tests cases."
                    }
                }
            }
        }
    }
}
JSON;

        $code = 400;
        $prophecy = $this->prophesize(ResponseInterface::class);
        $prophecy->getStatusCode()->willReturn($code);
        $prophecy->getHeader('Content-Type')->willReturn(['application/json']);

        $validator = new SwaggerValidator($this->buildSwagger($swagger));
        $validator->validateResponseFor($prophecy->reveal(), PathItem::METHOD_GET, '/tests', $code);
    }

    /**
     * @test
     * @expectedException \WakeOnWeb\Component\Swagger\Test\Exception\ContentTypeException
     */
    public function testValidateResponseForThrowsAnExceptionWhenTheContentTypeIsInvalid()
    {
        $swagger = <<<JSON
{
    "swagger": "2.0",
    "info": {
        "title": "test",
        "version": "1.0"
    },
    "produces": [
        "application/json"
    ],
    "paths": {
        "/tests": {
            "get": {
                "responses": {
                    "200": {
                        "description": "Get the list of all the tests cases."
                    }
                }
            }
        }
    }
}
JSON;

        $prophecy = $this->prophesize(ResponseInterface::class);
        $prophecy->getStatusCode()->willReturn(200);
        $prophecy->getHeader('Content-Type')->willReturn(['application/xml']);

        $validator = new SwaggerValidator($this->buildSwagger($swagger));
        $validator->validateResponseFor($prophecy->reveal(), PathItem::METHOD_GET, '/tests', 200);
    }

    /**
     * @test
     * @expectedException \WakeOnWeb\Component\Swagger\Test\Exception\UnknownPathException
     */
    public function testValidateResponseForThrowsAnExceptionWhenPathIsNotOnSchema()
    {
        $swagger = <<<JSON
{
    "swagger": "2.0",
    "info": {
        "title": "test",
        "version": "1.0"
    },
    "produces": [
        "application/json"
    ],
    "paths": {
        "/tests": {
            "get": {
                "responses": {
                    "200": {
                        "description": "Get the list of all the tests cases."
                    }
                }
            }
        }
    }
}
JSON;

        $code = 200;
        $prophecy = $this->prophesize(ResponseInterface::class);
        $prophecy->getStatusCode()->willReturn($code);
        $prophecy->getHeader('Content-Type')->willReturn(['application/json']);

        $validator = new SwaggerValidator($this->buildSwagger($swagger));
        $validator->validateResponseFor($prophecy->reveal(), PathItem::METHOD_GET, '/foobar', $code);
    }

    /**
     * @test
     * @expectedException \InvalidArgumentException
     */
    public function testValidateResponseForThrowsAnExceptionWhenTheMethodIsNotSupported()
    {
        $swagger = <<<JSON
{
    "swagger": "2.0",
    "info": {
        "title": "test",
        "version": "1.0"
    },
    "produces": [
        "application/json"
    ],
    "paths": {
        "/tests": {
            "get": {
                "responses": {
                    "200": {
                        "description": "Get the list of all the tests cases."
                    }
                }
            }
        }
    }
}
JSON;

        $validator = new SwaggerValidator($this->buildSwagger($swagger));
        $validator->validateResponseFor($this->prophesize(ResponseInterface::class)->reveal(), 'LINK', '/tests', 200);
    }

    /**
     * @test
     * @expectedException \InvalidArgumentException
     */
    public function testValidateResponseForHandlesBaseUrl()
    {
        $swagger = <<<JSON
{
    "swagger": "2.0",
    "basePath": "/foo",
    "info": {
        "title": "test",
        "version": "1.0"
    },
    "produces": [
        "application/json"
    ],
    "paths": {
        "/tests": {
            "get": {
                "responses": {
                    "200": {
                        "description": "Get the list of all the tests cases."
                    }
                }
            }
        }
    }
}
JSON;

        $validator = new SwaggerValidator($this->buildSwagger($swagger));
        $validator->validateResponseFor($this->prophesize(ResponseInterface::class)->reveal(), 'LINK', '/foo/tests', 200);
    }

    /**
     * @param string $swagger
     *
     * @return Swagger
     */
    private function buildSwagger($swagger)
    {
        $factory = new SwaggerFactory();

        return $factory->build(json_decode($swagger, true));
    }
}
