class Macro:
    def __init__(self, row):
        self.id = row['id']
        self.mesiac = row['mesiac']
        self.rok = row['rok']
        self.vysetrenie_start = row['vysetrenie_start']
        self.vysetrenie_koniec = row['vysetrenie_koniec']
        self.vypis_start = row['vypis_start']
        self.vypis_koniec = row['vypis_koniec']
        self.sestra_id = row['sestra_id']
        self.prvy_den = row['prvy_den']
        self.posledny_den = row['posledny_den']
