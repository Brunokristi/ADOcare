from collections import deque
import time

class Request_queue:
    """this class functional must be synchronized"""
    def __init__(self, count_of_access: int, window_length: int, request_time: int):
        """time_limits, request_time in seconds"""
        self.count_of_access: int = count_of_access
        self.window_length: int = window_length
        self.request_time: int = request_time

        self.queue = deque()

    def get_request_sleep_time(self) -> int:
        """return count of seconds to sleep before get access to api"""
        self.__clean_queue()
        if len(self.queue) >= self.count_of_access:
            return self.window_length - (time.time()-self.queue[0])

        return 0

    def register_request(self) -> None:
        self.queue.append(time.time())

    def __clean_queue(self) -> None:
        threshold = time.time()-self.window_length+self.request_time
        while self.queue and self.queue[0] < threshold:
            self.queue.popleft()
