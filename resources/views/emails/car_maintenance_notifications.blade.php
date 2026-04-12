@extends('emails.layout')

@section('content')
    <p>Dobrý deň,</p>

    <p>
        pre spoločnosť {{ $companyName ?? 'Vaša spoločnosť' }} je v blížiacej sa budúcnosti naplánovaná údržba vozidiel.
        Nižšie nájdete zoznam služieb, ktoré je potrebné riešiť:
    </p>

    @if (!empty($services))
        @foreach ($services as $service)
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin-bottom:8px;">
                <tr>
                    <td style="
                        background:#DEECEF;
                        border-radius:10px;
                        padding:10px 12px;
                        color:#575252;
                        font-size:12px;
                        line-height:1.4;
                    ">
                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                            <tr>
                                <td style="width:22px; vertical-align:middle; padding-right:8px;">
                                    <img
                                        src="https://adocare.sk/exclamation.svg"
                                        width="16"
                                        height="16"
                                        alt="car"
                                        style="display:block;fill:#F72585;"
                                    >
                                </td>

                                <td style="vertical-align:middle; color:#575252; font-size:12px; line-height:1.4;">
                                    <table role="presentation" cellspacing="0" cellpadding="0">
                                        <!-- Name -->
                                        <tr>
                                            <td style="font-weight:600; padding-bottom:2px;">
                                                {{ $service['name'] ?? 'Služba' }}
                                            </td>
                                        </tr>

                                        <!-- Car info -->
                                        <tr>
                                            <td style="padding-bottom:2px;">
                                                {{ $service['car_model'] ?? '-' }}
                                                &nbsp;•&nbsp;
                                                {{ $service['car_evc'] ?? '-' }}
                                                &nbsp;•&nbsp;
                                                vodič: {{ $service['driver_name'] ?? '-' }}
                                            </td>
                                        </tr>

                                        <!-- Date -->
                                        <tr>
                                            <td>
                                                termín: {{ $service['next_due_date'] ?? '-' }}
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        @endforeach
    @else
        <p>Pre toto obdobie neboli nájdené žiadne služby.</p>
    @endif

    <p>
        Tento email je automaticky generovaný systémom adocare. Na tento email neodpovedajte.
    </p>

    <p>
        Pre nastavenia upozornení a správu vozidiel navštívte <a href="https://adocare.studiokristian.com/manager/company/company" target="_blank" rel="noopener noreferrer" style="color: #ffffff; text-decoration: underline;">nastavenia</a>.
    </p>
    @endsection