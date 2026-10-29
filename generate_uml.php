<?php
$plantuml_code = <<<EOD
@startuml
left to right direction
skinparam packageStyle rectangle

actor "Admin (Farm Owner / Manager)" as Admin
actor "Staff (Farm Worker)" as Staff

rectangle "Integrated Poultry Farm Management System" {
    (Manage Batches) as BM1
    (View Batch Information) as BM2
    BM1 ..> BM2 : <<include>>

    (Record Activity / Assign Task) as FO1
    (Complete Pending Action) as FO2
    (View Activity Records) as FO3
    FO1 ..> FO3 : <<include>>
    FO2 ..> FO3 : <<include>>

    (Manage Health Records) as HR1
    (View Health History) as HR2
    HR1 ..> HR2 : <<include>>

    (Manage Mortality Records) as M1
    (View Mortality Records) as M2
    M1 ..> M2 : <<include>>

    (Manage Feed Consumption) as FC1
    (View Feed Consumption) as FC2
    FC1 ..> FC2 : <<include>>

    (Manage Stock Inventory) as IM1
    (View Inventory & Alerts) as IM2

    (Manage Retailers) as RM1

    (Create New Reservation) as RES1
    (Record Fulfillment / Pickup) as RES2
    (Edit / Cancel Reservation) as RES3
    (View Reservation Details) as RES4

    (Manage Expenses Ledger) as REP1
    (Generate System Reports) as REP2

    (Manage User Accounts) as SYS1
    (View System Audit Logs) as SYS2
    (Manage Farm Profile) as SYS3
}

Admin --> BM1
Staff --> BM2

Admin --> FO1
Admin --> FO2
Staff --> FO3

Admin --> HR1
Staff --> HR2

Admin --> M1
Staff --> M2

Admin --> FC1
Staff --> FC2

Admin --> IM1
Admin --> IM2

Admin --> RM1

Admin --> RES1
Admin --> RES2
Admin --> RES3
Admin --> RES4
Staff --> RES4

Admin --> REP1
Admin --> REP2

Admin --> SYS1
Admin --> SYS2
Admin --> SYS3
@enduml
EOD;

$compressed = gzcompress($plantuml_code, 9);
$encoded = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($compressed));

$url = "https://kroki.io/plantuml/png/" . $encoded;

$imgData = file_get_contents($url);
if ($imgData !== false) {
    $out_path = 'C:\Users\EJ Shane\.gemini\antigravity-ide\brain\5ad446c7-e1fa-4678-b0e9-af713ac593ae\use_case_diagram.png';
    file_put_contents($out_path, $imgData);
    echo "Saved diagram to $out_path\n";
} else {
    echo "Failed to download image.\n";
}
?>
