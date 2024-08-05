<?php

$port = 4000;

// Create server socket
$server = stream_socket_server("tcp://localhost:$port", $errno, $errstr);

if (!$server) {
    die("Error: $errstr ($errno)\n");
}

echo "Server running on Port $port\n";

while ($conn = stream_socket_accept($server)) {
    // Read request data
    $request = stream_get_contents($conn);
    $data = json_decode($request, true);

    // recieving the monthly input to caluculate operations
    $monthlyGrossIncome = isset($data['monthlyGrossIncome']) ? floatval($data['monthlyGrossIncome']) : 0;
    $monthlyExpenses = isset($data['monthlyExpenses']) ? floatval($data['monthlyExpenses']) : 0;

    // making operations to calculate yearly expenses
    $annualGrossIncome = $monthlyGrossIncome * 12;
    $annualExpenses = $monthlyExpenses * 12;

    // calculation of all addbacks for sde
    $yearlyAddBacks = [
        'MonthlyOwnerW2Wage' => isset($data['MonthlyOwnerW2Wage']) ? floatval($data['MonthlyOwnerW2Wage']) * 12 : 0,
        'Depreciation' => isset($data['Depreciation']) ? floatval($data['Depreciation']) * 12 : 0,
        'Interest' => isset($data['Interest']) ? floatval($data['Interest']) * 12 : 0,
        'MealsAndEntertainment' => isset($data['MealsAndEntertainment']) ? floatval($data['MealsAndEntertainment']) * 12 : 0,
        'PersonalTravel' => isset($data['PersonalTravel']) ? floatval($data['PersonalTravel']) * 12 : 0,
        'OwnerHealthAndLifeInsurance' => isset($data['OwnerHealthAndLifeInsurance']) ? floatval($data['OwnerHealthAndLifeInsurance']) * 12 : 0,
        'Owner401k' => isset($data['Owner401k']) ? floatval($data['Owner401k']) * 12 : 0,
        'OneTimeBusinessExpenses' => isset($data['OneTimeBusinessExpenses']) ? floatval($data['OneTimeBusinessExpenses']) * 12 : 0,
    ];
    $totalAddBacks = array_sum($yearlyAddBacks);

    //Caluculation for sde
    $MonthlySde = $monthlyGrossIncome - $monthlyExpenses + ($totalAddBacks / 12);
    $YearlySde = $MonthlySde * 12;
    //Calculations for ebida without W2
    $addBacksWithoutW2 = array_sum(array_filter($yearlyAddBacks, function ($key) {
        return $key !== "MonthlyOwnerW2Wage";
    }, ARRAY_FILTER_USE_KEY));
    $MonthlyEbitda = ($monthlyGrossIncome - $monthlyExpenses + $addBacksWithoutW2 / 12);
    $YearlyEbitda = $MonthlyEbitda * 12;

    //  income classification from request
    $sdeValuation = ['low' => 0, 'high' => 0, 'average' => 0];
    $ebitdaValuation = ['low' => 0, 'high' => 0, 'average' => 0];

    $incomeClassification = $data['incomeClassification'] ?? null;
    if ($incomeClassification === "non-recuring") {
        $sdeValuation['low'] = $YearlySde * 2.0;
        $sdeValuation['high'] = $YearlySde * 2.8;
        $sdeValuation['average'] = ($sdeValuation['low'] + $sdeValuation['high']) / 2;
        $ebitdaValuation['low'] = $YearlyEbitda * 2.5;
        $ebitdaValuation['high'] = $YearlyEbitda * 4.5;
        $ebitdaValuation['average'] = ($ebitdaValuation['low'] + $ebitdaValuation['high']) / 2;
    } elseif ($incomeClassification === "guaranteed-recurring") {
        $sdeValuation['low'] = $YearlySde * 3.5;
        $sdeValuation['high'] = $YearlySde * 4.5;
        $sdeValuation['average'] = ($sdeValuation['low'] + $sdeValuation['high']) / 2;
        $ebitdaValuation['low'] = $YearlyEbitda * 4.0;
        $ebitdaValuation['high'] = $YearlyEbitda * 5.0;
        $ebitdaValuation['average'] = ($ebitdaValuation['low'] + $ebitdaValuation['high']) / 2;
    }

    $response = json_encode([
        'annualGrossIncome' => $annualGrossIncome,
        'annualExpenses' => $annualExpenses,
        'yearlyAddBacks' => $yearlyAddBacks,
        'MonthlySde' => $MonthlySde,
        'YearlySde' => $YearlySde,
        'MonthlyEbitda' => $MonthlyEbitda,
        'YearlyEbitda' => $YearlyEbitda,
        'sdeValuation' => $sdeValuation,
        'ebitdaValuation' => $ebitdaValuation
    ]);

    // Send response
    fwrite($conn, "HTTP/1.1 200 OK\r\nContent-Type: application/json\r\nContent-Length: " . strlen($response) . "\r\n\r\n$response");
    fclose($conn);
}

// Close server socket
fclose($server);
?>
