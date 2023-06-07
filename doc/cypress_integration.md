DESIGN
======

A) response_device doesn't exist
  A.1) cypress is available
    A.1.i) user launch => send POST to Cypress; get back uuid; create response_device
    A.1.ii) user abort => n/a (impossible)
    A.1.iii) cypress abort => return 404
    A.1.iv) cypress complete => n/a (impossible)
  A.2) is busy
    A.2.i) user launch => get barcode and uuid from Cypress (status); create response_device or show busy in Pine UI
    A.2.ii) user abort => n/a (impossible)
    A.2.iii) cyrpess abort => return 404
    A.2.iv) cypress complete => return 404
      
B) response_device exists
  B.1) cypress is available
    B.1.i) user launch => reload Pine UI
    B.1.ii) user abort => send DELETE to Cypress (404 response); delete response_device
    B.1.iii) cypress abort => n/a (impossible)
    B.1.iv) cypress complete => n/a (impossible)
  B.2) is busy
    B.2.i) user launch => reload Pine UI
    B.2.ii) user abort => send DELETE to Cypress (200 response); delete response_device
    B.2.iii) cypress abort => delete response_device or return 404
    B.3.iv) cypress complete => complete response_device or return 404



IMPLEMENTATION
==============

launch( <answer_id> )
{
  Pine client sends PATCH to PINE:answer/<answer_id>?action=launch_device
  if( response_device record exists )
  {
    respond to client with uuid and status (from response_device record)
  }
  else
  {
    Pine server sends GET to CYPRESS:<device_path>/status
    if( cypress is available )
    {
      Pine server sends POST to CYPRESS:<device_path>
      if( UUID returned )
      {
        respond to client with uuid and status (from cypress)
      }
      else
      {
        respond to client with "cypress error"
      }
    }
    else if( cypress is in progress )
    {
      respond to client with "cypress is busy with another participant"
    }
    else
    {
      respond to client with "cypress is offline"
    }
  }
}

abort( <uuid> )
{
  Client or Cypress sends DELETE to PINE:response_device/<uuid>
  if( response_device record doesn't exist )
  {
    return 404
  }

  if( role is not cypress )
  {
    server sends DELETE to CYPRESS:<device_path>/<uuid>
  }
  server deletes the response_device record
}

complete( <uuid> )
{
  Cypress sends PATCH to PINE:answer/<answer_id> with body { "value": <DEVICE_JSON_OUTPUT> }
  if( answer record doesn't exist )
  {
    return 404
  }
  JSON data is stored to the answer

  for all data files in Cypress
  {
    Cypress sends PATCH to PINE:answer/<answer_id>?filename=<FILENAME> with raw file contents as body
  }

  Cypress sends PATCH to PINE:response_device/<uuid> with body { "status": "completed" }
  if( response_device record doesn't exist )
  {
    return 404
  }

  server updates response_device.status to "completed"
}



API
===

Pine:
  PATCH answer/<answer_id>?action=launch_device returns { "uuid": <uuid>, "status": "in progress" }
  PATCH answer/<answer_id> with body { "instrumentBarcode": <STRING>, "language": "en|fr", "startTime": <DATETIME>, "endTime": <DATETIME>, "results": [{<DEVICE_KEY_VALUE_PAIRS>}] }
  PATCH answer/<answer_id>?filename=<FILENAME> with raw file contents as body
  DELETE response_device/<uuid> returns 200 or 404
  PATCH response_device/<uuid> with body { "status": "completed" } returns 200 or 404

Cypress:
  GET <device_path>/status returns { "status": "ready|in progress", ... }
  POST <device_path> with body { "answer_id":, "barcode":, "language":, "interviewer": } returns UUID or 409
  DELETE <device_path>/<uuid> return 200 or 404
