<?php
////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//                                                                                                                                                                //
//                                                                ** SPLIT PHP FRAMEWORK **                                                                       //
// This file is part of *SPLIT PHP Framework*                                                                                                                     //
//                                                                                                                                                                //
// Why "SPLIT"? Firstly because the word "split" is a reference to micro-services and split systems architecture (of course you can make monoliths with it,       //
// if that's your thing). Furthermore, it is an acronym for these 5 bound concepts which are the bases that this framework leans on, which are: "Simplicity",     //
// "Purity", "Lightness", "Intuitiveness", "Target Minded"                                                                                                        //
//                                                                                                                                                                //
// See more info about it at: https://github.com/gabriel-guelfi/split-php                                                                                         //
//                                                                                                                                                                //
// MIT License                                                                                                                                                    //
//                                                                                                                                                                //
// Copyright (c) 2025 Lightertools Open Source Community                                                                                                          //
//                                                                                                                                                                //
// Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the "Software"), to          //
// deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or         //
// sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:                            //
//                                                                                                                                                                //
// The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.                                 //
//                                                                                                                                                                //
// THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,FITNESS     //
// FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY           //
// WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.     //
//                                                                                                                                                                //
////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

namespace SplitPHP\Exceptions;

use Throwable;
use Exception;

/**
 * Class UserException
 *
 * This class represents a user-defined exception in the SplitPHP framework.
 * It extends the base Exception class and can be used to handle user-specific errors.
 *
 * @package SplitPHP\Exceptions
 */
abstract class UserException extends Exception
{
  /**
   * @var int $statusCode The HTTP status code associated with this exception.
   */
  protected int $statusCode;

  /**
   * @var string $statusMessage The HTTP status message associated with this exception.
   */
  protected string $statusMessage;

  /**
   * @var bool $usrReadable Indicates whether the exception is user-readable.
   */
  protected bool $usrReadable = true;
  /**
   * UserException constructor.
   *
   * @param string $message The error message.
   * @param int $code The error code.
   * @param Throwable|null $previous The previous exception, if any.
   */
  public function __construct(string $message = "", int $code = 0, bool $usrReadable = true, ?Throwable $previous = null)
  {
    parent::__construct($message, $code, $previous);

    $this->statusCode = 0;
    $this->statusMessage = "";
    $this->usrReadable = $usrReadable;
  }

  /**
   * Returns a string representation of this class for printing purposes.
   *
   * @return string
   */
  public final function __toString(): string
  {
    return sprintf(
      "%s[%d] %s: %s",
      get_class($this),
      $this->statusCode,
      $this->statusMessage,
      $this->getMessage()
    );
  }

  /**
   * Returns the HTTP status code associated with this exception.
   *
   * @return int|null The HTTP status code or null if not set.
   */
  public final function getStatusCode(): ?int
  {
    return $this->statusCode;
  }

  /**
   * Returns the HTTP status message associated with this exception.
   *
   * @return string|null The HTTP status message or null if not set.
   */
  public final function getStatusMessage(): ?string
  {
    return $this->statusMessage;
  }

  /**
   * Returns whether the exception is user-readable.
   *
   * @return bool True if the exception is user-readable, false otherwise.
   */
  public final function isUserReadable(): bool
  {
    return $this->usrReadable;
  }
}
