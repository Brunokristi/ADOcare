class SizeMissMathException(Exception):
    def __init__(self):
        super().__init__("Sizes of received list miss math!")
