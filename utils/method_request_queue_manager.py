from utils.request_queue import Request_queue
from typing import List

class Method_request_queue_manager:
    def __init__(self, queues: List[Request_queue]):
        self.queues: List[Request_queue] = queues

    def get_request_sleep_time(self) -> int:
        time: int = 0
        for queue in self.queues:
            t = queue.get_request_sleep_time()
            if time < t:
                time = t

        return time

    def register_request(self):
        for queue in self.queues:
            queue.register_request()
