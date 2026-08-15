import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\RpsDeleteController::destroy
 * @see app/Http/Controllers/RpsDeleteController.php:12
 * @route '/rps/{rps}'
 */
export const destroy = (args: { rps: string | number } | [rps: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/rps/{rps}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\RpsDeleteController::destroy
 * @see app/Http/Controllers/RpsDeleteController.php:12
 * @route '/rps/{rps}'
 */
destroy.url = (args: { rps: string | number } | [rps: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { rps: args }
    }

    
    if (Array.isArray(args)) {
        args = {
                    rps: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        rps: args.rps,
                }

    return destroy.definition.url
            .replace('{rps}', parsedArgs.rps.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\RpsDeleteController::destroy
 * @see app/Http/Controllers/RpsDeleteController.php:12
 * @route '/rps/{rps}'
 */
destroy.delete = (args: { rps: string | number } | [rps: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

    /**
* @see \App\Http\Controllers\RpsDeleteController::destroy
 * @see app/Http/Controllers/RpsDeleteController.php:12
 * @route '/rps/{rps}'
 */
    const destroyForm = (args: { rps: string | number } | [rps: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: destroy.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'DELETE',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\RpsDeleteController::destroy
 * @see app/Http/Controllers/RpsDeleteController.php:12
 * @route '/rps/{rps}'
 */
        destroyForm.delete = (args: { rps: string | number } | [rps: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: destroy.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'DELETE',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    destroy.form = destroyForm
const RpsDeleteController = { destroy }

export default RpsDeleteController