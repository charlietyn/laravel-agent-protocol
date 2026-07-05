# ADP RFC

Agent Discovery Protocol (ADP) is a small HTTP metadata contract that lets an
external client discover how to call an API before sending business requests.

This repository implements ADP for Laravel. Other ecosystems can implement the
same HTTP contract without sharing PHP code.

## Core Rule

ADP endpoints describe capabilities. They do not execute capabilities.

The agent reads metadata from `/agent`, then decides which existing API endpoint
to call.
