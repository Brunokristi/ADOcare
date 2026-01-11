import copy
import numpy as np
from typing import List
from openrouteservice import Client
from easy.config import Config
from easy.logger import Logger
from ortools.constraint_solver import routing_enums_pb2
from ortools.constraint_solver import pywrapcp

from app.models.exceptions.pathNotFoundException import PathNotFoundException
from app.models.exceptions.sizeMissMathException import SizeMissMathException

class tsp_solver:
    _instance = None

    def __new__(cls, *args, **kwargs):
        if not cls._instance:
            cls._instance = super(tsp_solver, cls).__new__(cls)

        return cls._instance

    def __init__(self, config: Config = None, logger: Logger = None):
        if not hasattr(self, 'initialized'):
            self.initialized: bool = True

            if config is None:
                raise TypeError("Parameter \"config\" must be initialized!")

            self.config: Config = config
            self.logger: Logger = logger if logger else config.logger

            self.ors_client = Client(base_url=config.getValue("base url") + "/ors")

    def calculate_part(self, start_location: List[float], points_locations: List[List[float]], end_location: List[float] = None):
        if end_location is None:
            end_location = start_location

        points_locations.insert(0, start_location)
        points_locations.append(end_location)

        matrix = self.ors_client.distance_matrix(
            locations=points_locations,
            metrics=['distance', 'duration'],
            units='m'
        )

        data = {
            'time_matrix': np.round(matrix['durations']).astype(int).tolist(),
            'num_vehicles': 1,
            'start': 0,
            'end': len(matrix['durations']) - 1
        }

        manager = pywrapcp.RoutingIndexManager(
            len(data['time_matrix']),
            data['num_vehicles'],
            [data['start']],
            [data['end']]
        )

        routing = pywrapcp.RoutingModel(manager)

        def time_callback(from_index, to_index):
            from_node = manager.IndexToNode(from_index)
            to_node = manager.IndexToNode(to_index)

            return data['time_matrix'][from_node][to_node]

        transit_callback_index = routing.RegisterTransitCallback(time_callback)
        routing.SetArcCostEvaluatorOfAllVehicles(transit_callback_index)

        search_parameters = pywrapcp.DefaultRoutingSearchParameters()
        search_parameters.first_solution_strategy = routing_enums_pb2.FirstSolutionStrategy.PATH_CHEAPEST_ARC
        search_parameters.local_search_metaheuristic = routing_enums_pb2.LocalSearchMetaheuristic.GUIDED_LOCAL_SEARCH
        search_parameters.time_limit.seconds = self.config.getValue("optimization time limit")

        solution = routing.SolveWithParameters(search_parameters)

        if not solution:
            raise PathNotFoundException()

        result = []
        index = routing.Start(0)
        while not routing.IsEnd(index):
            prevIndex = manager.IndexToNode(index)
            index = solution.Value(routing.NextVar(index))
            result.append(
                {
                    "start": points_locations[prevIndex],
                    "end": points_locations[index],
                    "duration": float(matrix['durations'][prevIndex][index]),
                    "length" : float(matrix['distances'][prevIndex][index])
                }
            )

        return result

    def calculate_path_with_time_management(self, start_time: int, timeSpending: List[int], start_location: List[float], points_locations: List[List[float]], end_location: List[float] = None):
        if len(timeSpending) != len(points_locations):
            raise SizeMissMathException()

        data = self.calculate_part(start_location, copy.deepcopy(points_locations), end_location)

        data[0]['timestamps'] = {
            "leave_start_point": int(start_time),
            "arrive_end_point": int(start_time+data[0]["duration"]),
            "leave_end_point": int(start_time+data[0]["duration"]+timeSpending[0])
        }

        for i in range(1, len(data)):
            data[i]['timestamps'] = {
                "leave_start_point": int(data[i-1]['timestamps']["leave_end_point"]),
                "arrive_end_point": int(data[i-1]['timestamps']["leave_end_point"]+data[i]["duration"]),
                "leave_end_point": int(data[i-1]['timestamps']["leave_end_point"]+data[i]["duration"]+timeSpending[0])
            }

        return data
