<?php

namespace WakeOnWeb\Component\Swagger\Test;

use InvalidArgumentException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use WakeOnWeb\Component\Swagger\Specification\Operation;
use WakeOnWeb\Component\Swagger\Specification\Swagger;
use WakeOnWeb\Component\Swagger\Test\Exception\ContentTypeException;
use WakeOnWeb\Component\Swagger\Test\Exception\MethodNotAllowedForPathException;
use WakeOnWeb\Component\Swagger\Test\Exception\StatusCodeException;
use WakeOnWeb\Component\Swagger\Test\Exception\SwaggerValidatorException;
use WakeOnWeb\Component\Swagger\Test\Exception\UnknownResponseCodeException;
use WakeOnWeb\Component\Swagger\Test\Exception\UnknownPathException;

/**
 * @author Quentin Schuler <q.schuler@wakeonweb.com>
 */
class SwaggerValidator
{
    /**
     * @var Swagger
     */
    private $swagger;

    /**
     * @var ResponseValidatorInterface[]
     */
    private $responseValidators = [];

    /**
     * @var RequestValidatorInterface[]
     */
    private $requestValidators = [];

    /**
     * @param Swagger $swagger
     */
    public function __construct(Swagger $swagger)
    {
        $this->swagger = $swagger;
    }

    /**
     * @param ResponseValidatorInterface $responseValidator
     */
    public function registerResponseValidator(ResponseValidatorInterface $responseValidator)
    {
        $this->responseValidators[] = $responseValidator;
    }


    /**
     * @param RequestValidatorInterface $requestValidator
     */
    public function registerRequestValidator(RequestValidatorInterface $requestValidator)
    {
        $this->requestValidators[] = $requestValidator;
    }

    /**
     * Validates the given response against the current Swagger file. It will check that the status code is the one
     * we expects, the `Content-Type` of the response and eventually the structure of the content.
     *
     * @param ResponseInterface $actual The actual response to check.
     * @param string            $method The method of the endpoint to check.
     * @param string            $path   The path of the endpoint to check.
     * @param int               $code   The status code of the endpoint to check.
     *
     * @throws InvalidArgumentException  When the given `$method` is not one of the `PathItem::METHOD_*` constant value.
     * @throws SwaggerValidatorException When The response does not validate the specification.
     */
    public function validateResponseFor(ResponseInterface $actual, $method, $path, $code)
    {
        $operation = $this->getOperation($method, $this->stripBasePath($path));

        $response = $operation
            ->getResponses()
            ->getResponseFor($code)
        ;

        // In this case, the response given to us is not on the schema
        if ($response === null) {
            throw UnknownResponseCodeException::fromUnknownStatusCode($code);
        }

        if ($actual->getStatusCode() !== $code) {
            throw StatusCodeException::fromInvalidStatusCode($code, $actual->getStatusCode());
        }

        $produces = $operation->getProduces()->getProduces();

        $contentType = $actual->getHeader('Content-Type');

        if ($this->statusCodeMeetRequirements($code) && !array_intersect($contentType, $produces)) {
            throw ContentTypeException::fromInvalidContentType($contentType, $produces);
        }

        foreach ($this->responseValidators as $validator) {
            $validator->validateResponse($response, $actual);
        }
    }

    /**
     * Validates the given request against the current Swagger file. It will check that the request satisfies the
     * parameters we expects, the headers of the request and eventually the structure of the content.
     *
     * @param RequestInterface $actual The actual request to check.
     * @param string           $method The method of the endpoint to check.
     * @param string           $path   The path of the endpoint to check.
     *
     * @throws InvalidArgumentException  When the given `$method` is not one of the `PathItem::METHOD_*` constant value.
     * @throws SwaggerValidatorException When The request does not validate the specification.
     */
    public function validateRequestFor(RequestInterface $actual, $method, $path)
    {
        $operation = $this->getOperation($method, $this->stripBasePath($path));

        foreach ($this->requestValidators as $validator) {
            $validator->validateRequest($operation, $actual);
        }
    }

    /**
     * Removes the base path, if present, off the given path.
     *
     * @param string $path
     *
     * @return string
     */
    private function stripBasePath($path)
    {
        return \str_replace($this->swagger->getBasePath(), '', $path);
    }

    /**
     * @param string $method
     * @param string $path
     *
     * @return null|Operation
     *
     * @throws SwaggerValidatorException
     * @throws InvalidArgumentException When the given `$method` is not one of the `PathItem::METHOD_*` constant value.
     */
    private function getOperation($method, $path)
    {
        $pathItem = $this
            ->swagger
            ->getPaths()
            ->getPathItemFor($path);

        if ($pathItem === null) {
            throw UnknownPathException::fromUnknownPath($path);
        }

        $operation = $pathItem->getOperationFor($method);
        if ($operation === null) {
            throw MethodNotAllowedForPathException::fromNotAllowedMethod($method);
        }

        return $operation;
    }

    /**
     * Checks whether the status code is not 204 or 304 or is not in the informational range. Such responses does not
     * have any content nor Content-Type headers.
     *
     * @param int $code
     *
     * @return bool
     */
    private function statusCodeMeetRequirements($code)
    {
        return !in_array($code, [204, 304]) && substr((string) $code, 0, 1) !== '1';
    }
}
