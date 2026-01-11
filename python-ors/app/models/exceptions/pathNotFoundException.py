class PathNotFoundException(Exception):
    def __init__(self):
        super().__init__("Failed to found path!")
