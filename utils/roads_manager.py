from typing import Any, List, Tuple
from utils.database import get_db_connection
from openrouteservice import Client
from easy import Config, Logger
from time import sleep
import math

class Road_manager:
    """Singleton obj for using open route service api and managing hashes for it"""
    _instance = None

    def __new__(cls, *args, **kwargs):
        if not cls._instance:
            cls._instance = super(Road_manager, cls).__new__(cls)

        return cls._instance

    def __init__(self, config: Config = None, logger: Logger = None):
        if not hasattr(self, 'initialized'):
            self.initialized: bool = True

            if config is None:
                raise TypeError("Parameter \"config\" must be initialized!")

            self.config: Config = config
            self.logger: Logger = logger if logger else config.logger
            self._set_api_clients()

    def _set_api_clients(self) -> None:
            self.api_client_index: int = 0
            self.clients: List[Client] = []

            for api_key in self.config.getValue("open route", "API keys"):
                self.clients.append(Client(key=api_key, retry_over_query_limit=False))

    def addClient(self, client_coordinate: Tuple[float, float]) -> None:
        """In the absence of such parameters as latitude and longitude for the client,
        it adds them to the hash database table to avoid unnecessary requests to the server.

        Parameters:
        coordinate (latitude, longitude)
        + latitude (float): The latitude of the center point in decimal degrees.
        + longitude (float): The longitude of the center point in decimal degrees."""
        conn = get_db_connection()

        for organization in conn.execute("SELECT latitude, longitude FROM adosky", ()).fetchall():
            ados_coordinate = (organization["latitude"], organization["longitude"])

            if not self._search_road_in_cache(ados_coordinate, client_coordinate):
                data = self._calculate_travel_time_and_distance(ados_coordinate, client_coordinate)

                self._cache_road(start=ados_coordinate, end=client_coordinate, data=data)

        conn.close()

    def _search_road_in_cache(self, start: Tuple[float, float], end: Tuple[float, float]) -> Tuple[float, int]:
        conn = get_db_connection()

        start_lat_min, start_lat_max, start_lon_min, start_lon_max = self._calculate_bounds(start)
        end_lat_min, end_lat_max, end_lon_min, end_lon_max = self._calculate_bounds(end)

        rows = conn.execute("""
        SELECT distance, time FROM distance
        WHERE (
                latitude_start BETWEEN ? AND ?
            AND
                longitude_start BETWEEN ? AND ?
            AND
                latitude_end BETWEEN ? AND ?
            AND
                longitude_end BETWEEN ? AND ?
            )
        OR (
                latitude_start BETWEEN ? AND ?
            AND
                longitude_start BETWEEN ? AND ?
            AND
                latitude_end BETWEEN ? AND ?
            AND
                longitude_end BETWEEN ? AND ?
            )
        """, (start_lat_min, start_lat_max, start_lon_min, start_lon_max,
              end_lat_min, end_lat_max, end_lon_min, end_lon_max,
              end_lat_min, end_lat_max, end_lon_min, end_lon_max,
              start_lat_min, start_lat_max, start_lon_min, start_lon_max,
              )).fetchall()

        conn.close()

        if rows:
            return rows[0]["distance"], rows[0]["time"]

        return None, None

    def _cache_road(self, start: Tuple[float, float], end: Tuple[float, float], data: Tuple[float, int]) -> None:
        """save cache to database."""

        conn = get_db_connection()
        conn.execute("""
                    INSERT INTO distance
                    (latitude_start, longitude_start, latitude_end, longitude_end, distance, time)
                    VALUES(?, ?, ?, ?, ?, ?)""",
                    (
                        start[0], start[1], end[0], end[1], data[0], data[1]

                    ))
        conn.commit()

    def _calculate_travel_time_and_distance(self, start: Tuple[float, float], end: Tuple[float, float]) -> Tuple[float, int]:
        """
        Returns a tuple containing the distance between two points in meters and the time required for the journey in seconds.

        @param start: A tuple representing the starting point's coordinates (latitude, longitude).
        @param end: A tuple representing the ending point's coordinates (latitude, longitude).
        @return: A tuple where the first element is the distance in meters and the second element is the time in seconds.
        Returns (None, None) if an error occurs while fetching the path time.
        """
        routes = self.execute_open_route_request(method_name="directions",
            coordinates=[[start[1], start[0]], [end[1], end[0]]],
            profile='driving-car',
            format='json'
        )

        data = (routes["routes"][0]["summary"]["distance"], int(routes['routes'][0]['summary']['duration']))

        if self.config.getValue("open route", "cache all data from open route"):
            self._cache_road(start=start, end=end, data=data)

        return data

    def _toggle_clients_index(self) -> None:
        self.api_client_index = (self.api_client_index + 1) % len(self.clients)

    def _calculate_bounds(self, coordinate: Tuple[float, float]) -> Tuple[float, float, float, float]:
        """Calculates the bounding coordinates based on a given latitude and longitude as coordinate tuple.

        This method computes the minimum and maximum latitude and longitude values
        that define a rectangular area around the specified coordinates. The size of
        the area is determined by a configurable distance in meters.

        The distance is divided by the approximate number of meters per degree of
        latitude and longitude to calculate the corresponding degree offsets.

        Parameters:
        coordinate (latitude, longitude)
        + latitude (float): The latitude of the center point in decimal degrees.
        + longitude (float): The longitude of the center point in decimal degrees.

        Returns:
        tuple: A tuple containing the minimum latitude, maximum latitude,
            minimum longitude, and maximum longitude, respectively.
            The format is (min_latitude, max_latitude, min_longitude, max_longitude).
        """
        latitude = coordinate[0]
        longitude = coordinate[1]

        distance_meters = self.config.getValue("open route", "delta distance between coordinates for searching hashed paths, in meters")

        lat_degree = distance_meters / 111320
        lon_degree = distance_meters / (111320 * math.cos(math.radians(latitude)))

        return (latitude - lat_degree, latitude + lat_degree, longitude - lon_degree, longitude + lon_degree)

    def get_road_data(self, start: Tuple[float, float], end: Tuple[float, float], force_use_api: bool = False) -> Tuple[float, int]:
        if not force_use_api:
            data = self._search_road_in_cache(start, end)
            if data[0] is not None and data[1] is not None:
                return data

        return self._calculate_travel_time_and_distance(start, end)

    def execute_open_route_request(self, method_name: str, *args, **kwargs) -> Any:
        method = getattr(self._get_open_route_service_client(), method_name)

        if not callable(method):
            raise ValueError(f"Function '{method_name}' does not exist.")

        while True:
            backup_api_client_index: int = self.api_client_index

            try:
                return method(*args, **kwargs)

            except Exception as e:
                if e.message["error"] == "Rate Limit Exceeded":
                    prev_api_index: int = self.api_client_index
                    self._toggle_clients_index()

                    self.logger.inform(f"The request limit for API key number {prev_api_index} has been reached, switching to number {self.api_client_index}...")

                    if self.api_client_index == backup_api_client_index: # sleep because we created cycle
                        sleep(self.config.getValue("open route", "sleep time after unsuccessful polling of all API keys, in seconds"))

                else:
                    return None

    def _get_open_route_service_client(self) -> Client:
        return self.clients[self.api_client_index]
