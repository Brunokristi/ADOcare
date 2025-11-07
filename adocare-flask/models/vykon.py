class Vykon:
    def __init__(self, row):
        self.vykon = row['code']
        self.popis = row['description']
        self.cena25 = row['poistovna25']
        self.cena24 = row['poistovna24']
        self.cena27 = row['poistovna27']
