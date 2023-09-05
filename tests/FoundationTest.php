<?php

declare(strict_types=1);

namespace Ebln\Tests\Html\TableTranspose;

use Ebln\Html\TableTranspose\Transposer;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class FoundationTest extends TestCase
{
    public function testCaseZero(): void
    {
        $input = <<<'EOF'
            <table>
                <tr>
                    <td>A1</td>
                    <td>A2</td>
                    <td>A3</td>
                </tr>
                <tr>
                    <td>B1</td>
                    <td>B2</td>
                    <td>B3</td>
                </tr>
            </table>
            EOF;

        $expectation = <<<'EOF'
            <table>
            <tr>
            <td>A1</td>
            <td>B1</td>
            </tr>
            <tr>
            <td>A2</td>
            <td>B2</td>
            </tr>
            <tr>
            <td>A3</td>
            <td>B3</td>
            </tr>
            </table>
            EOF;

        $service = new Transposer();
        $output  = $service->transpose($input);
        self::assertSame($expectation, $output);
    }

    public function testCaseOne(): void
    {
        $input = <<<'EOF'
                <table>
                    <thead><tr>
                        <th scope="row" colspan="2">0</th>
                        <th scope="col">2</th>
                        <th scope="col">3</th>
                        <th scope="col">4</th>
                    </tr></thead>
                    <tbody>
                        <tr>
                            <th scope="row" colspan="2"><span class="leitwort">A0</span></th>
                            <td>A2</td>
                            <td>A3</td>
                            <td>A4</td>
                        </tr>
                        <tr><th colspan="5">Inter TH1</th></tr>
                        <tr>
                            <th scope="row" rowspan="4">B0</th>
                            <th scope="row" style="background-color: aquamarine">C1</th>
                            <td style="background-color: aquamarine">C2</td>
                            <td style="background-color: aquamarine">C3</td>
                            <td style="background-color: aquamarine">C4</td>
                        </tr>
                        <tr>
                            <th scope="row" style="background-color: darksalmon">D1</th>
                            <td style="background-color: darksalmon">D2</td>
                            <td style="background-color: darksalmon">D3</td>
                            <td style="background-color: darksalmon">D4</td>
                        </tr>
                        <tr>
                            <th scope="row" style="background-color: mediumpurple">E1</th>
                            <td style="background-color: mediumpurple">E2</td>
                            <td style="background-color: mediumpurple">E3</td>
                            <td style="background-color: mediumpurple">E4</td>
                        </tr>
                        <tr>
                            <th scope="row" style="background-color: mediumspringgreen">F1</th>
                            <td style="background-color: mediumspringgreen">F2</td>
                            <td style="background-color: mediumspringgreen">F3</td>
                            <td style="background-color: mediumspringgreen">F4</td>
                        </tr>
                        <tr><th colspan="5">Inter TH2</th></tr>
                        <tr>
                            <th scope="row" colspan="2">G0</th>
                            <td>G2</td>
                            <td>G3</td>
                            <td>G4</td>
                        </tr>
                    </tbody>
                </table>
            EOF;

        $expectation = <<<'EOF'
            <table><tbody>
            <tr>
            <th scope="col" rowspan="2">0</th>
            <th scope="col" rowspan="2"><span class="leitwort">A0</span></th>
            <th rowspan="5">Inter TH1</th>
            <th scope="col" colspan="4">B0</th>
            <th rowspan="5">Inter TH2</th>
            <th scope="col" rowspan="2">G0</th>
            </tr>
            <tr>
            <th scope="col" style="background-color: aquamarine">C1</th>
            <th scope="col" style="background-color: darksalmon">D1</th>
            <th scope="col" style="background-color: mediumpurple">E1</th>
            <th scope="col" style="background-color: mediumspringgreen">F1</th>
            </tr>
            <tr>
            <th scope="row">2</th>
            <td>A2</td>
            <td style="background-color: aquamarine">C2</td>
            <td style="background-color: darksalmon">D2</td>
            <td style="background-color: mediumpurple">E2</td>
            <td style="background-color: mediumspringgreen">F2</td>
            <td>G2</td>
            </tr>
            <tr>
            <th scope="row">3</th>
            <td>A3</td>
            <td style="background-color: aquamarine">C3</td>
            <td style="background-color: darksalmon">D3</td>
            <td style="background-color: mediumpurple">E3</td>
            <td style="background-color: mediumspringgreen">F3</td>
            <td>G3</td>
            </tr>
            <tr>
            <th scope="row">4</th>
            <td>A4</td>
            <td style="background-color: aquamarine">C4</td>
            <td style="background-color: darksalmon">D4</td>
            <td style="background-color: mediumpurple">E4</td>
            <td style="background-color: mediumspringgreen">F4</td>
            <td>G4</td>
            </tr>
            </tbody></table>
            EOF;

        $service = new Transposer();
        $output  = $service->transpose($input);
        self::assertSame($expectation, $output);
    }
}
