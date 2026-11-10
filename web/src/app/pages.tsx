import { Link, isRouteErrorResponse, useRouteError } from 'react-router-dom';

export function NotFound() {
  return (
    <div className="grid h-full place-items-center p-8 text-center">
      <div><h1 className="text-2xl font-bold">Page not found</h1><p className="mb-4 mt-1 text-ink-soft">That address does not exist or has moved.</p><Link to="/inbox" className="btn btn-primary">Go to inbox</Link></div>
    </div>
  );
}

export function RouteError() {
  const e = useRouteError();
  const text = isRouteErrorResponse(e) ? `${e.status} ${e.statusText}` : e instanceof Error ? e.message : 'Unknown error';
  return (
    <div className="grid h-full place-items-center p-8 text-center" role="alert">
      <div><h1 className="text-2xl font-bold">Something broke on this page</h1><p className="mb-4 mt-1 text-ink-soft">{text}</p>
        <button className="btn btn-primary" onClick={() => location.assign('/inbox')}>Back to inbox</button></div>
    </div>
  );
}
